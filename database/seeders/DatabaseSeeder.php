<?php

namespace Database\Seeders;

use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageCodeGenerator;
use App\Services\GarageRuleService;
use App\Services\RentalCostCalculator;
use App\Services\RentalPeriodCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Initial garage count and prices per type: [count, daily price, price per kWh].
     */
    private const SAMPLE_KTP = __DIR__.'/files/ktp-sample.png';

    private const GARAGES = [
        'bicycle' => [10, 5000, null],
        'car' => [10, 30000, null],
        'electric' => [5, 50000, GarageRuleService::DEFAULT_KWH_PRICE],
    ];

    public function __construct(
        private readonly GarageCodeGenerator $codeGenerator,
        private readonly RentalPeriodCalculator $periodCalculator,
        private readonly RentalCostCalculator $costCalculator,
        private readonly GarageRuleService $garageRules,
    ) {}

    public function run(): void
    {
        $this->resetUploadedFiles();

        User::factory()->admin()->create([
            'nik' => '3171000000000001',
            'name' => 'Administrator',
            'phone' => '081200000001',
            'email' => 'admin@example.com',
        ]);

        $budi = $this->createRenter('Budi Santoso', 'budi@example.com', '3171010101900001', '081234560001');
        $siti = $this->createRenter('Siti Rahmawati', 'siti@example.com', '3273020202920002', '081234560002');
        $this->createRenter('Andi Pratama', 'pending@example.com', '3578030303940003', '081234560003', UserStatus::Pending);
        $this->createRenter('Rina Kurnia', 'rejected@example.com', '3374040404960004', '081234560004', UserStatus::Rejected);

        $budiBicycle = Vehicle::factory()->bicycle()->for($budi)->create(['brand_model' => 'Polygon Heist 2']);
        $budiCar = Vehicle::factory()->car()->for($budi)->create(['brand_model' => 'Toyota Avanza', 'plate_number' => 'B 1234 KLM']);
        $budiElectric = Vehicle::factory()->electricCar(60)->for($budi)->create(['brand_model' => 'Hyundai Ioniq 5', 'plate_number' => 'B 2001 EVA']);

        $sitiMotorcycle = Vehicle::factory()->motorcycle()->for($siti)->create(['brand_model' => 'Honda Vario 125', 'plate_number' => 'D 4567 ABC']);
        $sitiCar = Vehicle::factory()->car()->for($siti)->create(['brand_model' => 'Honda Jazz', 'plate_number' => 'D 7788 XY']);
        $sitiElectric = Vehicle::factory()->electricCar(75)->for($siti)->create(['brand_model' => 'BYD Atto 3', 'plate_number' => 'D 3003 EVB']);

        $garages = $this->createGarages();

        $today = CarbonImmutable::today();
        $twoMonthsAgo = $today->subMonthsNoOverflow(2)->startOfMonth();
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();

        $this->createRental($budiCar, $garages['C001'], RentalType::Daily, $twoMonthsAgo->addDays(4), 3, PaymentStatus::Verified);
        // Monthly rental spanning two months: starts on the 15th, ends on the 14th of the next month.
        $this->createRental($sitiCar, $garages['C001'], RentalType::Monthly, $twoMonthsAgo->addDays(14), null, PaymentStatus::Verified);
        $this->createRental($sitiElectric, $garages['E001'], RentalType::Monthly, $lastMonth, null, PaymentStatus::Verified, 25);
        $this->createRental($sitiMotorcycle, $garages['S001'], RentalType::Daily, $lastMonth->addDays(9), 5, PaymentStatus::Rejected);

        // Running monthly rental that the admin can extend.
        $this->createRental($sitiMotorcycle, $garages['S003'], RentalType::Monthly, $today->subDays(10), null, PaymentStatus::Verified);

        $extended = $this->createRental($budiCar, $garages['C002'], RentalType::Daily, $today->subDay(), 3, PaymentStatus::Verified);
        $this->extendRental($extended, RentalType::Daily, 2);

        // Electric car in an electric garage: initial charging with the rental, then additional chargings.
        $charged = $this->createRental($budiElectric, $garages['E002'], RentalType::Daily, $today->subDays(2), 7, PaymentStatus::Verified, 30);
        $this->createAdditionalCharging($charged, 50, PaymentStatus::Verified, $today->subDay());
        $this->createAdditionalCharging($charged, 70, PaymentStatus::Pending, $today);

        // Two back-to-back rentals in C003 to demo the maximum extension recommendation.
        $this->createRental($sitiCar, $garages['C003'], RentalType::Daily, $today, 3, PaymentStatus::Verified);
        $this->createRental($sitiElectric, $garages['C003'], RentalType::Daily, $today->addDays(5), 3, PaymentStatus::Verified);

        $this->createRental($budiBicycle, $garages['S002'], RentalType::Daily, $today, 2, PaymentStatus::Pending);
    }

    private function createRenter(string $name, string $email, string $nik, string $phone, UserStatus $status = UserStatus::Approved): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'nik' => $nik,
            'phone' => $phone,
            'role' => UserRole::Renter,
            'status' => $status,
            'ktp_path' => $this->copySampleKtp(),
        ]);
    }

    /**
     * @return array<string, Garage>
     */
    private function createGarages(): array
    {
        $garages = [];

        foreach (self::GARAGES as $type => [$count, $dailyPrice, $kwhPrice]) {
            $garageType = GarageType::from($type);

            foreach ($this->codeGenerator->nextNumbers($garageType, $count) as $number) {
                $garage = Garage::create([
                    'type' => $garageType,
                    'number' => $number,
                    'daily_price' => $dailyPrice,
                    'kwh_price' => $kwhPrice,
                ]);

                $garages[$garage->code] = $garage;
            }
        }

        return $garages;
    }

    private function createRental(
        Vehicle $vehicle,
        Garage $garage,
        RentalType $type,
        CarbonImmutable $startDate,
        ?int $days,
        PaymentStatus $paymentStatus,
        ?int $batteryPercent = null,
    ): Rental {
        $createdAt = $startDate->min(CarbonImmutable::now());

        $rental = Rental::create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garage->id,
            'rental_type' => $type,
            'start_date' => $startDate,
            'end_date' => $this->periodCalculator->calculateEndDate($type, $startDate, $days),
            'payment_proof' => $this->createPlaceholderImage('public', 'payments', 'Rental payment proof'),
            'payment_status' => $paymentStatus,
        ]);
        $rental->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        if ($this->garageRules->chargesVehicle($garage->type, $vehicle->type)) {
            $charging = $rental->chargings()->create([
                'current_battery_percent' => $batteryPercent,
                'payment_proof' => null,
                'payment_status' => $paymentStatus,
            ]);
            $charging->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        }

        return $rental;
    }

    private function extendRental(Rental $rental, RentalType $type, ?int $days): void
    {
        $extension = $this->costCalculator->calculateExtension($type, $days, (float) $rental->garage->daily_price, $rental->end_date);

        $rental->update([
            'end_date' => $extension['end_date'],
            'extension_days' => $rental->extension_days + $extension['days'],
            'extension_cost' => (float) $rental->extension_cost + $extension['cost'],
            'extension_payment_proof' => $this->createPlaceholderImage('public', 'extensions', 'Extension payment proof'),
        ]);
    }

    private function createAdditionalCharging(Rental $rental, int $batteryPercent, PaymentStatus $paymentStatus, CarbonImmutable $createdAt): void
    {
        $charging = $rental->chargings()->create([
            'current_battery_percent' => $batteryPercent,
            'payment_proof' => $this->createPlaceholderImage('public', 'chargings', 'Charging payment proof'),
            'payment_status' => $paymentStatus,
        ]);
        $charging->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }

    private function resetUploadedFiles(): void
    {
        Storage::disk('local')->deleteDirectory('ktp');

        foreach (['payments', 'extensions', 'chargings'] as $directory) {
            Storage::disk('public')->deleteDirectory($directory);
        }
    }

    /**
     * Copy the sample KTP image (fictional data) to the private disk for each seeded renter.
     */
    private function copySampleKtp(): string
    {
        $path = 'ktp/'.Str::random(40).'.png';
        Storage::disk('local')->put($path, file_get_contents(self::SAMPLE_KTP));

        return $path;
    }

    /**
     * Create a placeholder image so seeded payment proofs can be displayed.
     */
    private function createPlaceholderImage(string $disk, string $directory, string $text): string
    {
        $image = imagecreatetruecolor(480, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 226, 232, 240));
        imagestring($image, 5, 24, 140, $text, imagecolorallocate($image, 30, 41, 59));

        ob_start();
        imagejpeg($image);
        $content = ob_get_clean();
        imagedestroy($image);

        $path = $directory.'/'.Str::random(40).'.jpg';
        Storage::disk($disk)->put($path, $content);

        return $path;
    }
}
