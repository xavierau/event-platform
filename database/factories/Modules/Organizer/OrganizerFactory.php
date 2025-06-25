use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizerFactory extends Factory
{
public function definition(): array
{
$name = $this->faker->company;

return [
'name' => [
'en' => $name,
'zh-TW' => $this->faker->company,
'zh-CN' => $this->faker->company,
],
'slug' => Str::slug($name).'-'.uniqid(),
'description' => [
'en' => $this->faker->paragraph,
'zh-TW' => $this->faker->paragraph,
'zh-CN' => $this->faker->paragraph,
],
];
}
}
