<?php

namespace App\DataTransferObjects\Payment;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\DataCollection;

class ItemData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,
        #[Nullable, StringType, Max(1000)]
        public ?string $description,
        #[Required, IntegerType, Min(50)] // Amount in cents
        public int $amount,
        #[Required, StringType, Size(3)]
        public string $currency,
        #[Required, IntegerType, Min(1)]
        public int $quantity,
    ) {}
}

class CreateCheckoutSessionData extends Data
{
    public function __construct(
        #[Required, ArrayType, Min(1)]
        /** @var DataCollection<ItemData> */
        public DataCollection $items,
        // #[Nullable, StringType, Exists('orders', 'id')] // Example if you add order_id validation
        // public ?string $order_id,
    ) {}

    // public static function rules(): array
    // {
    //     // You can define more complex rules here if attribute-based validation is not enough
    //     return [
    //         // 'order_id' => 'nullable|string|exists:orders,id',
    //     ];
    // }
}
