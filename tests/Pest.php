<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(PHPUnit\Framework\TestCase::class)->in('Unit');
// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that certain values match certain expectations.
| The "expect()" function can be used to build these expectations, and it's especially useful when
| you're working with objects or arrays.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out of the box, you may have some testing code specific to your
| project that you'd like to reuse across your tests. Functions are the perfect place to
| put this code.
|
*/

function something()
{
    // ..
}
