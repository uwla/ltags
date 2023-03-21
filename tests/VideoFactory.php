<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'title'  => $this->faker->sentence(),
            'url'   => $this->faker->url(),
        ];
    }
}
