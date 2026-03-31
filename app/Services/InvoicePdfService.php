<?php

namespace App\Services;

use App\Models\FuelRequest;
use Illuminate\Support\Str;

class InvoicePdfService
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const LEFT_MARGIN = 40.0;
    private const RIGHT_MARGIN = 555.28;

    /** @var list<string> */
    private array $commands = [];

    public function render(FuelRequest $order): string
    {
        $billing = $order->billing;
        $generatedAt = now()->format('d M Y, h:i A');
        $status = strtoupper((string) ($billing?->billing_status ?? 'estimated'));
        $deliveryCharge = (float) ($order->slab_charge ?: ($billing?->delivery_charge ?? $order->delivery_charge));

        $charges = [
            ['label' => 'Fuel total', 'amount' => (float) ($billing?->fuel_total ?? 0)],
            ['label' => 'Delivery charge', 'amount' => $deliveryCharge],
        ];

        if ((float) $order->night_fee > 0) {
            $charges[] = ['label' => 'Night delivery extra', 'amount' => (float) $order->night_fee];
        }

        $charges[] = ['label' => 'Platform fee', 'amount' => (float) ($billing?->platform_fee ?? 0)];
        $charges[] = [
            'label' => 'GST (' . number_format((float) ($billing?->gst_percent ?? 18), 0) . '%)',
            'amount' => (float) ($billing?->gst_amount ?? 0),
        ];
        $charges[] = ['label' => 'Total amount', 'amount' => (float) ($billing?->total_amount ?? $order->total_amount)];

        $this->commands = [];

        $this->drawText('FuelMate Invoice', self::LEFT_MARGIN, 800, 'F2', 24, [0.09, 0.25, 0.47]);
        $this->drawText(
            'Fuel delivery billing summary and settlement snapshot',
            self::LEFT_MARGIN,
            780,
            'F1',
            11,
            [0.38, 0.38, 0.38]
        );
        $this->drawText(
            'Order #' . $order->displayOrderNumber(),
            self::LEFT_MARGIN,
            756,
            'F2',
            14
        );
        $this->drawText(
            'Generated ' . $generatedAt,
            360,
            756,
            'F1',
            11,
            [0.38, 0.38, 0.38]
        );
        $this->drawText(
            'Billing status: ' . $status,
            360,
            738,
            'F2',
            11,
            [0.09, 0.25, 0.47]
        );
        $this->drawLine(self::LEFT_MARGIN, 724, self::RIGHT_MARGIN, 724, [0.82, 0.86, 0.91], 1.2);

        $this->drawSectionTitle('Order Details', 698);
        $this->drawInfoBlock(40, 678, 'Order Number', '#' . $order->displayOrderNumber());
        $this->drawInfoBlock(210, 678, 'Order Date', $order->created_at->format('d M Y, h:i A'));
        $this->drawInfoBlock(380, 678, 'Payment Method', $order->paymentMethodLabel());
        $this->drawInfoBlock(40, 634, 'Fuel Type', (string) ($order->fuelProduct->name ?? 'Not available'));
        $this->drawInfoBlock(210, 634, 'Quantity', number_format((float) $order->quantity_liters, 2) . ' L');
        $this->drawInfoBlock(
            380,
            634,
            'Rate',
            'INR ' . number_format((float) ($billing?->fuel_price_per_liter ?? $order->fuel_price_per_liter), 2) . '/L'
        );

        $this->drawSectionTitle('Billing Breakdown', 582);
        $this->drawLine(self::LEFT_MARGIN, 570, self::RIGHT_MARGIN, 570, [0.88, 0.9, 0.93], 0.8);

        $y = 548;
        foreach ($charges as $index => $charge) {
            $isTotal = $index === array_key_last($charges);

            $this->drawText(
                $charge['label'],
                self::LEFT_MARGIN,
                $y,
                $isTotal ? 'F2' : 'F1',
                $isTotal ? 13 : 11,
                $isTotal ? [0.1, 0.1, 0.1] : [0.32, 0.32, 0.32]
            );
            $this->drawText(
                'INR ' . number_format((float) $charge['amount'], 2),
                410,
                $y,
                $isTotal ? 'F2' : 'F1',
                $isTotal ? 13 : 11,
                $isTotal ? [0.09, 0.25, 0.47] : [0.1, 0.1, 0.1]
            );

            if (! $isTotal) {
                $this->drawLine(self::LEFT_MARGIN, $y - 10, self::RIGHT_MARGIN, $y - 10, [0.93, 0.94, 0.96], 0.6);
            }

            $y -= $isTotal ? 28 : 24;
        }

        $this->drawSectionTitle('Delivery Parties', 390);
        $this->drawInfoBlock(40, 370, 'Customer', (string) ($order->user->name ?? 'Not available'));
        $this->drawInfoBlock(210, 370, 'Customer Email', (string) ($order->user->email ?? 'Not available'));
        $this->drawInfoBlock(380, 370, 'Agent', (string) ($order->agent->user->name ?? 'Awaiting assignment'));
        $this->drawInfoBlock(40, 326, 'Agent Vehicle', (string) ($order->agent->vehicle_type ?? 'Not available'));
        $this->drawWrappedInfoBlock(210, 326, 'Delivery Address', (string) $order->delivery_address, 30);

        $notesStartY = 250;
        $this->drawSectionTitle('Invoice Notes', $notesStartY);
        $notes = [
            'GST and platform fee are stored centrally in FuelMate billing records.',
            'Agent and admin settlement values are prepared for payout and refund workflows.',
            'For COD orders, payment status turns paid after customer-verified delivery handoff.',
        ];

        $noteY = $notesStartY - 24;
        foreach ($notes as $note) {
            $this->drawText('- ' . $note, self::LEFT_MARGIN, $noteY, 'F1', 11, [0.32, 0.32, 0.32]);
            $noteY -= 20;
        }

        $this->drawLine(self::LEFT_MARGIN, 112, self::RIGHT_MARGIN, 112, [0.88, 0.9, 0.93], 0.8);
        $this->drawText(
            'This invoice was generated electronically by FuelMate.',
            self::LEFT_MARGIN,
            92,
            'F1',
            10,
            [0.45, 0.45, 0.45]
        );

        return $this->buildPdf(implode("\n", $this->commands));
    }

    private function drawSectionTitle(string $text, float $y): void
    {
        $this->drawText($text, self::LEFT_MARGIN, $y, 'F2', 14, [0.09, 0.25, 0.47]);
    }

    private function drawInfoBlock(float $x, float $y, string $label, string $value): void
    {
        $this->drawText($label, $x, $y, 'F1', 10, [0.45, 0.45, 0.45]);
        $this->drawText($value, $x, $y - 16, 'F2', 11, [0.1, 0.1, 0.1]);
    }

    private function drawWrappedInfoBlock(float $x, float $y, string $label, string $value, int $maxChars): void
    {
        $this->drawText($label, $x, $y, 'F1', 10, [0.45, 0.45, 0.45]);

        $lineY = $y - 16;
        foreach ($this->wrapText($value, $maxChars) as $line) {
            $this->drawText($line, $x, $lineY, 'F2', 11, [0.1, 0.1, 0.1]);
            $lineY -= 14;
        }
    }

    private function drawLine(float $x1, float $y1, float $x2, float $y2, array $color, float $width): void
    {
        $this->commands[] = sprintf(
            'q %.2F w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S Q',
            $width,
            $color[0],
            $color[1],
            $color[2],
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    private function drawText(
        string $text,
        float $x,
        float $y,
        string $font,
        float $size,
        array $color = [0.1, 0.1, 0.1]
    ): void {
        $cleanText = $this->escapeText($text);

        $this->commands[] = sprintf(
            'q %.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q',
            $color[0],
            $color[1],
            $color[2],
            $font,
            $size,
            $x,
            $y,
            $cleanText
        );
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $maxChars): array
    {
        $clean = Str::ascii(Str::squish($text));

        if ($clean === '') {
            return ['Not available'];
        }

        return preg_split('/\r\n|\r|\n/', wordwrap($clean, $maxChars, "\n", true)) ?: [$clean];
    }

    private function escapeText(string $text): string
    {
        $text = Str::ascii(Str::squish($text));

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text
        );
    }

    private function buildPdf(string $contentStream): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 7\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= 6; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
