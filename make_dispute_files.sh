cat << 'MIG' > backend/database/migrations/2026_09_28_000002_create_operational_disputes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_disputes');
    }
};
MIG

cat << 'MOD' > backend/app/Models/OperationalDispute.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalDispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'reporter_id',
        'reason',
        'description',
        'status',
        'resolution',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
MOD

cat << 'FAC' > backend/database/factories/OperationalDisputeFactory.php
<?php

namespace Database\Factories;

use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperationalDisputeFactory extends Factory
{
    protected $model = OperationalDispute::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'resolution' => null,
        ];
    }
}
FAC

cat << 'CON' > backend/app/Http/Controllers/Api/V1/OperationalDisputeController.php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OperationalDispute;
use Illuminate\Http\Request;

class OperationalDisputeController extends Controller
{
    public function index()
    {
        return response()->json(OperationalDispute::with(['order', 'reporter'])->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reporter_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $dispute = OperationalDispute::create($validated);

        return response()->json($dispute, 201);
    }

    public function update(Request $request, OperationalDispute $operationalDispute)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,under_review,resolved,closed',
            'resolution' => 'nullable|string',
        ]);

        $operationalDispute->update($validated);

        return response()->json($operationalDispute);
    }
}
CON

cat << 'TES' > backend/tests/Feature/OperationalDisputeTest.php
<?php

namespace Tests\Feature;

use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_disputes(): void
    {
        OperationalDispute::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/disputes');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_can_create_dispute(): void
    {
        $order = Order::factory()->create();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/disputes', [
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'reason' => 'Service not provided',
            'description' => 'The guide did not show up.',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['reason' => 'Service not provided']);
                 
        $this->assertDatabaseHas('operational_disputes', [
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'reason' => 'Service not provided',
        ]);
    }
}
TES

