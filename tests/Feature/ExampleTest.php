<?php

it('can test', function () {
    expect(true)->toBeTrue();

});

it('has seeded adminn users', function () {
    $this->assertDatabaseHas('users', ['email' => 'admin@domain.com']);
});

it('has seeded export schedules', function () {
    $this->assertDatabaseCount('export_schedules',1);
});
