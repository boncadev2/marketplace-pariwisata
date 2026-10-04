import os
import datetime

# Helper to write files
def write_file(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w') as f:
        f.write(content.strip() + "\n")

# Base paths
base_dir = "backend"
migrations_dir = os.path.join(base_dir, "database/migrations")
models_dir = os.path.join(base_dir, "app/Models")
seeders_dir = os.path.join(base_dir, "database/seeders")

timestamp = datetime.datetime.now().strftime("%Y_%m_%d_%H%M%S")

# Fase 33: PilotDatabaseSeeder
write_file(os.path.join(seeders_dir, "PilotDatabaseSeeder.php"), f"""
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Destination;
use App\Models\Ticket;
use App\Models\Category;
use Illuminate\Support\Facades\Hash;

class PilotDatabaseSeeder extends Seeder
{{
    public function run(): void
    {{
        // Create Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin_pilot@example.com'],
            [
                'name' => 'Admin Pilot',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ]
        );

        // Create Staff
        $staff = User::firstOrCreate(
            ['email' => 'staff_pilot@example.com'],
            [
                'name' => 'Staff Pilot',
                'password' => Hash::make('password'),
                'role' => 'staff'
            ]
        );

        // Category
        $category = Category::firstOrCreate([
            'name' => 'Wisata Alam',
            'slug' => 'wisata-alam',
        ]);

        // Destination in Kabupaten (e.g., Bandung)
        $destination = Destination::firstOrCreate([
            'name' => 'Tangkuban Perahu Pilot',
            'slug' => 'tangkuban-perahu-pilot',
        ], [
            'category_id' => $category->id,
            'description' => 'Beautiful mountain destination.',
            'location' => 'Kabupaten Bandung Barat',
            'latitude' => -6.7596,
            'longitude' => 107.6098,
        ]);

        // Tickets
        Ticket::firstOrCreate([
            'destination_id' => $destination->id,
            'name' => 'Tiket Masuk Reguler',
        ], [
            'price' => 30000,
            'stock' => 100,
        ]);
        
        Ticket::firstOrCreate([
            'destination_id' => $destination->id,
            'name' => 'Tiket Kendaraan (Mobil)',
        ], [
            'price' => 20000,
            'stock' => 50,
        ]);
    }}
}}
""")

# Fase 34 Migrations and Models

write_file(os.path.join(models_dir, "RoomType.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'capacity'];

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_create_room_types_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
""")

write_file(os.path.join(models_dir, "RoomInventory.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventory extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'date', 'stock'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_01_create_room_inventories_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_inventories');
    }
};
""")

write_file(os.path.join(models_dir, "RoomRate.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'date', 'price'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_02_create_room_rates_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
""")

write_file(os.path.join(models_dir, "LodgingBooking.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LodgingBooking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'room_type_id', 'check_in', 'check_out', 'total_price', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_03_create_lodging_bookings_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodging_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->decimal('total_price', 15, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lodging_bookings');
    }
};
""")

# Fase 35 Migrations and Models

write_file(os.path.join(models_dir, "CulinaryPlace.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CulinaryPlace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'location'];

    public function mealSlots(): HasMany
    {
        return $this->hasMany(MealSlot::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_04_create_culinary_places_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('culinary_places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('culinary_places');
    }
};
""")

write_file(os.path.join(models_dir, "MealSlot.php"), """
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealSlot extends Model
{
    use HasFactory;

    protected $fillable = ['culinary_place_id', 'time_slot', 'package_name', 'price', 'capacity'];

    public function culinaryPlace(): BelongsTo
    {
        return $this->belongsTo(CulinaryPlace::class);
    }
}
""")

write_file(os.path.join(migrations_dir, f"{timestamp}_05_create_meal_slots_table.php"), """
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culinary_place_id')->constrained()->cascadeOnDelete();
            $table->dateTime('time_slot');
            $table->string('package_name');
            $table->decimal('price', 15, 2);
            $table->integer('capacity')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_slots');
    }
};
""")

print("Files generated successfully.")
