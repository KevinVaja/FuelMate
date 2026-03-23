<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addAcademicColumnsToUsers();
        $this->addAcademicColumnsToAgents();
        $this->addAcademicColumnsToFuelRequests();

        $this->createAdminsTable();
        $this->createPaymentsTable();
        $this->createLocationsTable();
        $this->createFeedbackTable();

        $this->backfillAcademicUserColumns();
        $this->backfillAcademicAgentColumns();
        $this->backfillAcademicFuelRequestColumns();
        $this->backfillAdminsTable();
        $this->backfillPaymentsTable();
        $this->backfillLocationsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('admins');

        if (Schema::hasColumn('fuel_requests', 'requestId')
            || Schema::hasColumn('fuel_requests', 'userId')
            || Schema::hasColumn('fuel_requests', 'fuelType')
            || Schema::hasColumn('fuel_requests', 'quantity')) {
            Schema::table('fuel_requests', function (Blueprint $table) {
                $table->dropColumn(['requestId', 'userId', 'fuelType', 'quantity']);
            });
        }

        if (Schema::hasColumn('agents', 'agentId')
            || Schema::hasColumn('agents', 'name')
            || Schema::hasColumn('agents', 'phone')
            || Schema::hasColumn('agents', 'fuel_availability')
            || Schema::hasColumn('agents', 'address')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn(['agentId', 'name', 'phone', 'fuel_availability', 'address']);
            });
        }

        if (Schema::hasColumn('users', 'userId') || Schema::hasColumn('users', 'location')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['userId', 'location']);
            });
        }
    }

    private function addAcademicColumnsToUsers(): void
    {
        $needsUserId = ! Schema::hasColumn('users', 'userId');
        $needsLocation = ! Schema::hasColumn('users', 'location');

        if (! $needsUserId && ! $needsLocation) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($needsUserId, $needsLocation) {
            if ($needsUserId) {
                $table->unsignedBigInteger('userId')->nullable()->unique();
            }

            if ($needsLocation) {
                $table->string('location', 255)->nullable();
            }
        });
    }

    private function addAcademicColumnsToAgents(): void
    {
        $needsAgentId = ! Schema::hasColumn('agents', 'agentId');
        $needsName = ! Schema::hasColumn('agents', 'name');
        $needsPhone = ! Schema::hasColumn('agents', 'phone');
        $needsFuelAvailability = ! Schema::hasColumn('agents', 'fuel_availability');
        $needsAddress = ! Schema::hasColumn('agents', 'address');

        if (! $needsAgentId && ! $needsName && ! $needsPhone && ! $needsFuelAvailability && ! $needsAddress) {
            return;
        }

        Schema::table('agents', function (Blueprint $table) use (
            $needsAgentId,
            $needsName,
            $needsPhone,
            $needsFuelAvailability,
            $needsAddress
        ) {
            if ($needsAgentId) {
                $table->unsignedBigInteger('agentId')->nullable()->unique();
            }

            if ($needsName) {
                $table->string('name', 100)->nullable();
            }

            if ($needsPhone) {
                $table->string('phone', 20)->nullable();
            }

            if ($needsFuelAvailability) {
                $table->decimal('fuel_availability', 10, 2)->default(0);
            }

            if ($needsAddress) {
                $table->string('address', 255)->nullable();
            }
        });
    }

    private function addAcademicColumnsToFuelRequests(): void
    {
        $needsRequestId = ! Schema::hasColumn('fuel_requests', 'requestId');
        $needsUserId = ! Schema::hasColumn('fuel_requests', 'userId');
        $needsFuelType = ! Schema::hasColumn('fuel_requests', 'fuelType');
        $needsQuantity = ! Schema::hasColumn('fuel_requests', 'quantity');

        if (! $needsRequestId && ! $needsUserId && ! $needsFuelType && ! $needsQuantity) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) use ($needsRequestId, $needsUserId, $needsFuelType, $needsQuantity) {
            if ($needsRequestId) {
                $table->unsignedBigInteger('requestId')->nullable()->unique();
            }

            if ($needsUserId) {
                $table->unsignedBigInteger('userId')->nullable()->index();
            }

            if ($needsFuelType) {
                $table->string('fuelType', 50)->nullable();
            }

            if ($needsQuantity) {
                $table->decimal('quantity', 10, 2)->nullable();
            }
        });
    }

    private function createAdminsTable(): void
    {
        if (Schema::hasTable('admins')) {
            return;
        }

        Schema::create('admins', function (Blueprint $table) {
            $table->id('adminId');
            $table->string('username', 50);
            $table->string('password');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private function createPaymentsTable(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id('paymentId');
            $table->unsignedBigInteger('userId')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('paymentmode', 50);
            $table->string('status', 20);
            $table->foreignId('fuel_request_id')->nullable()->unique()->constrained('fuel_requests')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private function createLocationsTable(): void
    {
        if (Schema::hasTable('locations')) {
            return;
        }

        Schema::create('locations', function (Blueprint $table) {
            $table->id('locationId');
            $table->unsignedBigInteger('userId')->nullable()->index();
            $table->decimal('Latitude', 10, 6);
            $table->decimal('Longitude', 10, 6);
            $table->string('Address', 255);
            $table->foreignId('fuel_request_id')->nullable()->unique()->constrained('fuel_requests')->cascadeOnDelete();
            $table->string('location_mode', 20)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }

    private function createFeedbackTable(): void
    {
        if (Schema::hasTable('feedback')) {
            return;
        }

        Schema::create('feedback', function (Blueprint $table) {
            $table->id('feedbackId');
            $table->unsignedBigInteger('userId')->nullable()->index();
            $table->string('comments', 400);
            $table->unsignedTinyInteger('rating');
            $table->foreignId('fuel_request_id')->nullable()->unique()->constrained('fuel_requests')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function backfillAcademicUserColumns(): void
    {
        $latestLocations = [];

        foreach (DB::table('fuel_requests')
            ->select('user_id', 'delivery_address', 'updated_at')
            ->whereNotNull('delivery_address')
            ->orderBy('user_id')
            ->orderByDesc('updated_at')
            ->get() as $request) {
            $latestLocations[$request->user_id] ??= $request->delivery_address;
        }

        foreach (DB::table('users')->select('id')->get() as $user) {
            $payload = ['userId' => $user->id];

            if (array_key_exists($user->id, $latestLocations)) {
                $payload['location'] = $latestLocations[$user->id];
            }

            DB::table('users')->where('id', $user->id)->update($payload);
        }
    }

    private function backfillAcademicAgentColumns(): void
    {
        foreach (DB::table('agents')->select('id', 'user_id', 'is_available')->get() as $agent) {
            $user = DB::table('users')
                ->select('name', 'phone', 'location')
                ->where('id', $agent->user_id)
                ->first();

            $latestDeliveryAddress = DB::table('fuel_requests')
                ->where('agent_id', $agent->id)
                ->whereNotNull('delivery_address')
                ->orderByDesc('updated_at')
                ->value('delivery_address');

            DB::table('agents')
                ->where('id', $agent->id)
                ->update([
                    'agentId' => $agent->id,
                    'name' => $user?->name,
                    'phone' => $user?->phone,
                    'fuel_availability' => $agent->is_available ? 1 : 0,
                    'address' => $latestDeliveryAddress ?: ($user?->location ?: 'Address not provided'),
                ]);
        }
    }

    private function backfillAcademicFuelRequestColumns(): void
    {
        foreach (DB::table('fuel_requests')->select('id', 'user_id', 'fuel_product_id', 'quantity_liters')->get() as $request) {
            $fuelType = DB::table('fuel_products')
                ->where('id', $request->fuel_product_id)
                ->value('fuel_type');

            DB::table('fuel_requests')
                ->where('id', $request->id)
                ->update([
                    'requestId' => $request->id,
                    'userId' => $request->user_id,
                    'fuelType' => $fuelType,
                    'quantity' => $request->quantity_liters,
                ]);
        }
    }

    private function backfillAdminsTable(): void
    {
        foreach (DB::table('users')
            ->select('id', 'name', 'email', 'password', 'phone')
            ->where('role', 'admin')
            ->get() as $adminUser) {
            $username = Str::limit(
                Str::before((string) $adminUser->email, '@') ?: Str::slug((string) $adminUser->name, ''),
                50,
                ''
            );

            DB::table('admins')->updateOrInsert(
                ['user_id' => $adminUser->id],
                [
                    'username' => $username !== '' ? $username : 'admin' . $adminUser->id,
                    'password' => $adminUser->password,
                    'email' => $adminUser->email,
                    'phone' => $adminUser->phone,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function backfillPaymentsTable(): void
    {
        foreach (DB::table('fuel_requests')
            ->select('id', 'user_id', 'total_amount', 'payment_method', 'payment_status', 'created_at', 'updated_at')
            ->get() as $request) {
            DB::table('payments')->updateOrInsert(
                ['fuel_request_id' => $request->id],
                [
                    'userId' => $request->user_id,
                    'amount' => $request->total_amount,
                    'paymentmode' => $request->payment_method,
                    'status' => $request->payment_status,
                    'created_at' => $request->created_at ?? now(),
                    'updated_at' => $request->updated_at ?? now(),
                ]
            );
        }
    }

    private function backfillLocationsTable(): void
    {
        foreach (DB::table('fuel_requests')
            ->select('id', 'user_id', 'delivery_lat', 'delivery_lng', 'delivery_address', 'location_mode', 'updated_at', 'created_at')
            ->whereNotNull('delivery_lat')
            ->whereNotNull('delivery_lng')
            ->whereNotNull('delivery_address')
            ->get() as $request) {
            DB::table('locations')->updateOrInsert(
                ['fuel_request_id' => $request->id],
                [
                    'userId' => $request->user_id,
                    'Latitude' => round((float) $request->delivery_lat, 6),
                    'Longitude' => round((float) $request->delivery_lng, 6),
                    'Address' => $request->delivery_address,
                    'location_mode' => $request->location_mode,
                    'captured_at' => $request->updated_at ?? $request->created_at ?? now(),
                    'created_at' => $request->created_at ?? now(),
                    'updated_at' => $request->updated_at ?? now(),
                ]
            );
        }
    }
};
