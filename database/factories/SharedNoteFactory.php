<?php

    namespace Database\Factories;

    use App\Models\User;
    use Illuminate\Database\Eloquent\Factories\Factory;

    class SharedNoteFactory extends Factory
    {
        public function definition(): array
        {
            return [
                'title' => $this->faker->realText(20),
                'content' => $this->faker->realText(200),
                'author_id' => User::inRandomOrder()->first()->id,
                'color' => $this->faker->randomElement(['yellow', 'blue', 'green', 'pink', 'purple']),
                'priority' => $this->faker->randomElement(['high', 'medium', 'low']),
                'deadline' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 month'),
            ];
        }
    }