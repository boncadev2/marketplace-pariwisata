mkdir -p backend/database/migrations
mkdir -p backend/app/Models
mkdir -p backend/app/Http/Controllers/Api/V1
mkdir -p backend/database/factories
mkdir -p backend/tests/Feature

cat << 'MIG' > backend/database/migrations/2026_09_28_000001_create_reviews_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
MIG

cat << 'MOD' > backend/app/Models/Review.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
MOD

cat << 'FAC' > backend/database/factories/ReviewFactory.php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'order_id' => Order::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
FAC

cat << 'CON' > backend/app/Http/Controllers/Api/V1/ReviewController.php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        return response()->json(Review::with(['user', 'product'])->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'order_id' => 'nullable|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review = Review::create($validated);

        return response()->json($review, 201);
    }

    public function show(Review $review)
    {
        return response()->json($review->load(['user', 'product']));
    }
}
CON

cat << 'TES' > backend/tests/Feature/ReviewTest.php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_reviews(): void
    {
        Review::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/reviews');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_can_create_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Excellent!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['rating' => 5]);
                 
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);
    }
}
TES

