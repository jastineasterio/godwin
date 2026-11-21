<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Office Chair', 'Desktop Computer', 'Printer', 'Whiteboard',
                'Desk', 'Bookshelf', 'Toy Block Set', 'First Aid Kit',
                'Water Dispenser', 'Microwave', 'Refrigerator', 'Television',
                'Football', 'Projector', 'Laptop', 'Tablet',
            ]),
            'category' => $this->faker->randomElement([
                'furniture', 'electronics', 'teaching', 'sports', 'kitchen', 'bedding', 'toys', 'other',
            ]),
            'asset_code' => 'GW-' . $this->faker->unique()->numerify('####'),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 20),
            'purchase_date' => $this->faker->dateBetween('-2 years', 'today'),
            'purchase_price' => $this->faker->randomFloat(2, 10, 5000),
            'supplier' => $this->faker->company(),
            'location' => $this->faker->randomElement([
                'KG1 Classroom', 'KG2 Classroom', 'Nursery Room', 'Office', 'Staff Room', 'Kitchen', 'Hall',
            ]),
            'condition' => $this->faker->randomElement(['new', 'good', 'fair', 'damaged', 'missing']),
            'status' => $this->faker->randomElement(['active', 'in_use', 'maintenance', 'disposed', 'missing']),
            'recorded_by' => null,
        ];
    }
}
