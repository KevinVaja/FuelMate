<?php

namespace App\Http\Controllers;

use App\Models\FuelRequest;
use App\Services\OrderCancellationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderCancellationController extends Controller
{
    public function customerCancel(Request $request, int $id, OrderCancellationService $orderCancellationService)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'cancellation_charge_payment_method' => 'nullable|in:wallet,online',
        ]);

        $order = FuelRequest::query()
            ->where('user_id', Auth::id())
            ->with('billing')
            ->findOrFail($id);

        try {
            if ($order->customerCancellationRequiresChargeSettlement()) {
                $paymentMethod = $data['cancellation_charge_payment_method']
                    ?? FuelRequest::CANCELLATION_CHARGE_METHOD_ONLINE;

                $orderCancellationService->payCancellationChargeAndCancel(
                    $order,
                    $data['reason'],
                    $paymentMethod,
                );

                return back()->with('success', 'Cancellation fee paid and order cancelled successfully.');
            }

            $orderCancellationService->cancelOrder($order, FuelRequest::CANCELLED_BY_CUSTOMER, $data['reason']);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order cancellation requested successfully.');
    }

    public function agentCancel(Request $request, int $id, OrderCancellationService $orderCancellationService)
    {
        $data = $request->validate([
            'reason' => 'required|in:Vehicle issue,Fuel unavailable,Location unreachable,Emergency',
        ]);

        $order = FuelRequest::query()
            ->where('agent_id', Auth::user()->agent?->id)
            ->with('billing')
            ->findOrFail($id);

        try {
            $orderCancellationService->cancelOrder($order, FuelRequest::CANCELLED_BY_AGENT, $data['reason']);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order cancelled and recorded with the selected reason.');
    }

    public function adminCancel(Request $request, int $id, OrderCancellationService $orderCancellationService)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $order = FuelRequest::query()
            ->with('billing')
            ->findOrFail($id);

        try {
            $orderCancellationService->cancelOrder(
                $order,
                FuelRequest::CANCELLED_BY_ADMIN,
                $data['reason'] ?: 'Cancelled by admin.'
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order force cancelled successfully.');
    }

    public function approveRefund(int $id, OrderCancellationService $orderCancellationService)
    {
        $order = FuelRequest::query()
            ->with('billing')
            ->findOrFail($id);

        try {
            $orderCancellationService->approveRefund($order);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Refund approved and wallet rollback completed.');
    }
}
