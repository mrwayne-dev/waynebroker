<?php

use App\Support\Money\Cents;
use App\Support\Money\Usd;

/**
 * Retires Maveren audit finding M-19.
 *
 * Maveren stored money as DECIMAL(15,2) and handled it as PHP floats at every
 * step: floatval() at the request boundary, float subtraction in the withdrawal
 * path, number_format() on display in eleven places with four different
 * results. The audit found fractions of a cent accumulating between a balance
 * and the sum of its own transactions and could not establish which was right.
 *
 * Cents is the type that makes the float unrepresentable, and Usd is the one
 * place a number becomes a string.
 */
test('a float is refused', function () {
    // The headline case. Note this test file has no declare(strict_types=1):
    // that is deliberate, because strict_types binds the calling file, and a
    // boundary that only rejects floats for callers who opted in is not a
    // boundary. If Cents::from took a typed int parameter, PHP would quietly
    // coerce this to 1 here and carry on.
    expect(fn () => Cents::from(1.5))->toThrow(TypeError::class);
});

test('a float that happens to be whole is refused too', function () {
    // 100.0 is the dangerous one: it looks exact, converts without complaint,
    // and is how a float gets into a system that believed it had excluded them.
    expect(fn () => Cents::from(100.0))->toThrow(TypeError::class);
});

test('a numeric string is refused', function () {
    // A string is what arrives from a request. Accepting it here would move the
    // boundary inside the value object, which is the one place it must not be.
    expect(fn () => Cents::from('100'))->toThrow(TypeError::class);
    expect(fn () => Cents::from(null))->toThrow(TypeError::class);
});

test('a negative amount is refused unless it is asked for', function () {
    expect(fn () => Cents::from(-5))->toThrow(InvalidArgumentException::class);

    expect(Cents::from(-5, allowNegative: true)->toInt())->toBe(-5);
});

test('an amount survives the round trip', function () {
    foreach ([0, 1, 99, 100, 123456, PHP_INT_MAX] as $amount) {
        expect(Cents::from($amount)->toInt())->toBeInt()->toBe($amount);
    }

    expect(Cents::from(PHP_INT_MIN, allowNegative: true)->toInt())->toBe(PHP_INT_MIN);
});

test('equality is by value', function () {
    expect(Cents::from(100)->equals(Cents::from(100)))->toBeTrue()
        ->and(Cents::from(100)->equals(Cents::from(101)))->toBeFalse()
        ->and(Cents::zero()->isZero())->toBeTrue()
        ->and(Cents::from(-1, allowNegative: true)->isNegative())->toBeTrue();
});

test('the formatter matches the shared display contract', function () {
    // The same file the Vitest suite reads. If PHP and the client ever disagree
    // about a cent, one of these two tests fails and names the case.
    $contract = json_decode(
        (string) file_get_contents(base_path('tests/fixtures/money-formatting.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($contract)->toBeArray()->toHaveKey('cases');
    expect($contract['cases'])->not->toBeEmpty();

    foreach ($contract['cases'] as $case) {
        $cents = Cents::from($case['cents'], allowNegative: true);

        expect(Usd::format($cents))->toBe($case['plain'], "cents {$case['cents']}")
            ->and(Usd::formatWithSymbol($cents))->toBe($case['withSymbol'], "cents {$case['cents']}");
    }
});

test('the formatter is exact past the range a float can hold', function () {
    // These are outside JavaScript's safe integer range, so they are not in the
    // shared contract. They are the reason Usd groups digits by string surgery
    // rather than calling number_format, which takes a float: above 2^53 the
    // grouping would be applied to digits that were already wrong.
    expect(Usd::format(Cents::from(intdiv(PHP_INT_MAX, 2))))
        ->toBe('46,116,860,184,273,879.03');

    expect(Usd::format(Cents::from(PHP_INT_MAX)))
        ->toBe('92,233,720,368,547,758.07');

    expect(Usd::format(Cents::from(PHP_INT_MIN, allowNegative: true)))
        ->toBe('-92,233,720,368,547,758.08');
});
