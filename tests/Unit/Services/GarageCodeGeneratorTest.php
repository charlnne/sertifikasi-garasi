<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Services\GarageCodeGenerator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarageCodeGeneratorTest extends TestCase
{
    private GarageCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new GarageCodeGenerator;
    }

    public static function firstCodeProvider(): array
    {
        return [
            'first bicycle garage' => [GarageType::Bicycle, 0, 'S001'],
            'tenth car garage' => [GarageType::Car, 9, 'C010'],
            'boundary: 999th electric garage' => [GarageType::Electric, 998, 'E999'],
        ];
    }

    #[DataProvider('firstCodeProvider')]
    public function test_build_numbers_after_last_number_returns_next_code(GarageType $type, int $lastNumber, string $expected): void
    {
        $numbers = $this->generator->buildNumbers($type, $lastNumber, 1);

        $this->assertSame([$expected], $this->generator->codesFor($type, $numbers));
    }

    public function test_build_numbers_multiple_garages_returns_sequential_codes(): void
    {
        $numbers = $this->generator->buildNumbers(GarageType::Car, 8, 3);

        $this->assertSame([9, 10, 11], $numbers);
        $this->assertSame(['C009', 'C010', 'C011'], $this->generator->codesFor(GarageType::Car, $numbers));
    }

    public static function validCountProvider(): array
    {
        return [
            'boundary: 1 garage' => [1],
            'boundary: 999 garages' => [999],
        ];
    }

    #[DataProvider('validCountProvider')]
    public function test_build_numbers_valid_count_returns_that_many_numbers(int $count): void
    {
        $result = $this->generator->buildNumbers(GarageType::Bicycle, 0, $count);

        $this->assertCount($count, $result);
        $this->assertSame(1, $result[0]);
        $this->assertSame($count, end($result));
    }

    public static function invalidCountProvider(): array
    {
        return [
            'boundary: 0 garages' => [0],
            'boundary: 1000 garages' => [1000],
            'negative: negative count' => [-5],
        ];
    }

    #[DataProvider('invalidCountProvider')]
    public function test_build_numbers_invalid_count_throws_exception(int $count): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Garage count must be between 1 and 999.');

        $this->generator->buildNumbers(GarageType::Bicycle, 0, $count);
    }

    public function test_build_numbers_exceeding_quota_throws_exception_with_remaining_quota(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only 1 more bicycle garage can be added (maximum 999).');

        $this->generator->buildNumbers(GarageType::Bicycle, 998, 2);
    }

    public function test_build_numbers_exactly_fill_quota_returns_numbers_until999(): void
    {
        $result = $this->generator->buildNumbers(GarageType::Electric, 997, 2);

        $this->assertSame(['E998', 'E999'], $this->generator->codesFor(GarageType::Electric, $result));
    }

    public function test_build_numbers_quota_full_throws_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Car garage quota is full (maximum 999).');

        $this->generator->buildNumbers(GarageType::Car, 999, 1);
    }
}
