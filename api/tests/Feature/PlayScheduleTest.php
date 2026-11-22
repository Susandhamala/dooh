<?php

use App\Support\LoopPacking;
use App\Support\PlaySchedule;

it('lists every loop that starts inside the window', function () {
    // 90 s loop: 40 an hour.
    $starts = PlaySchedule::loopStarts(90, 8 * 60, 9 * 60);
    expect($starts)->toHaveCount(40)
        ->and(PlaySchedule::clock($starts[1]))->toBe('08:01:30')
        ->and(PlaySchedule::clock(end($starts)))->toBe('08:58:30');
    // 08:15–08:45 on a 120 s loop: 08:16 … 08:44.
    expect(PlaySchedule::loopStarts(120, 495, 525))->toHaveCount(15);
});

it('restarts the loop every hour, so a loop that does not divide the hour leaves a gap', function () {
    // 70 s loop: 51 loops (3570 s) then 30 s of nothing before the next hour.
    $starts = PlaySchedule::loopStarts(70, 8 * 60, 10 * 60);
    expect($starts)->toHaveCount(102)->and(PlaySchedule::clock($starts[51]))->toBe('09:00:00');
});

it('packs ads back to back, first copies first', function () {
    $places = LoopPacking::pack([
        ['key' => 'a', 'slots' => 1, 'repeats' => 2],
        ['key' => 'b', 'slots' => 4, 'repeats' => 1],
        ['key' => 'c', 'slots' => 2, 'repeats' => 3],
    ]);
    // copy 1: a 0, b 1–4, c 5–6 · copy 2: a 7, c 8–9 · copy 3: c 10–11
    expect($places)->toBe(['a' => [0, 7], 'b' => [1], 'c' => [5, 8, 10]])
        ->and(LoopPacking::used([['slots' => 1, 'repeats' => 2], ['slots' => 4, 'repeats' => 1]]))->toBe(6);
});

it('reads and writes clock times', function () {
    expect(PlaySchedule::minutes('08:15'))->toBe(495)
        ->and(PlaySchedule::minutes('24:00'))->toBe(1440)
        ->and(PlaySchedule::time(495))->toBe('08:15');
});
