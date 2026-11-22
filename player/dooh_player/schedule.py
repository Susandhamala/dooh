"""Which ad is on screen at a given moment (see the schedule notes in manifest.py)."""

from __future__ import annotations

from .manifest import Item, Manifest


def slot_at(m: Manifest, now: float) -> tuple[int | None, float]:
    """The slot playing at `now`, and seconds until the next slot starts.

    None when `now` is in the end of an hour too short for a whole loop (loop length that
    doesn't divide the hour), which stays empty until the next hour.
    """
    pos = (now + m.tz_offset_s) % 3600
    loops = 3600 // m.loop_seconds
    if pos >= loops * m.loop_seconds:
        return None, 3600 - pos
    in_loop = pos % m.loop_seconds
    return int(in_loop // m.spot_seconds), m.spot_seconds - in_loop % m.spot_seconds


def item_for(m: Manifest, slot: int, now: float) -> Item | None:
    """The item whose slots include `slot` and that is booked for `now`."""
    for item in m.items:
        if item.covers(slot) and item.plays_at(int(now)):
            return item
    return None


def seconds_left(m: Manifest, item: Item, slot: int, wait: float) -> float:
    """Seconds until `item` ends, from inside `slot` with `wait` seconds of that slot left."""
    return wait + (item.slot_index + item.slot_count - 1 - slot) * m.spot_seconds
