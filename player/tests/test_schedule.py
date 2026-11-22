from dooh_player.manifest import Item, Manifest
from dooh_player.schedule import item_for, seconds_left, slot_at

# 10:00:00 in Kathmandu (UTC+5:45) on 10 Nov 2026.
TEN_AM = 1_794_284_100
NPT = 20700


def manifest(items=(), loop=120, spot=10) -> Manifest:
    return Manifest(screen_id="s", version=1, valid_from=0, expires_at=2**40, tz_offset_s=NPT,
                    loop_seconds=loop, spot_seconds=spot, items=tuple(items))


def item(slot: int, start: int, end: int) -> Item:
    return Item(creative_id=f"c{slot}", url="/m", sha256="a" * 64, duration_s=10, slot_index=slot, windows=((start, end),))


def test_loop_restarts_at_the_local_hour():
    assert (TEN_AM + NPT) % 3600 == 0
    m = manifest()
    assert slot_at(m, TEN_AM) == (0, 10)
    assert slot_at(m, TEN_AM + 9.5) == (0, 0.5)
    assert slot_at(m, TEN_AM + 35) == (3, 5)
    assert slot_at(m, TEN_AM + 120) == (0, 10)  # next loop
    assert slot_at(m, TEN_AM + 3600 - 1) == (11, 1)


def test_end_of_hour_that_does_not_fit_a_loop_stays_empty():
    m = manifest(loop=70, spot=10)  # 51 loops = 3570 s, then 30 s until the hour
    assert slot_at(m, TEN_AM + 3570) == (None, 30)
    assert slot_at(m, TEN_AM + 3600) == (0, 10)


def test_item_plays_only_in_its_slot_and_window():
    booked = item(3, TEN_AM, TEN_AM + 7200)
    m = manifest([booked])
    assert item_for(m, 3, TEN_AM + 35) is booked
    assert item_for(m, 2, TEN_AM + 25) is None
    assert item_for(m, 3, TEN_AM - 85) is None  # same slot, an hour before the booking
    assert item_for(m, 3, TEN_AM + 7200 + 35) is None


def test_longer_ad_covers_its_slots_and_runs_to_its_last_one():
    # A 34 s video on 10 s slots takes slots 2–5 (20 s to 60 s into each loop).
    long = Item(creative_id="v", url="/m", sha256="a" * 64, duration_s=40, slot_index=2, windows=((TEN_AM, TEN_AM + 3600),), slot_count=4)
    m = manifest([long])
    assert item_for(m, 1, TEN_AM + 15) is None
    for at in (20, 35, 59):
        assert item_for(m, slot_at(m, TEN_AM + at)[0], TEN_AM + at) is long
    assert item_for(m, 6, TEN_AM + 65) is None
    slot, wait = slot_at(m, TEN_AM + 20)
    assert seconds_left(m, long, slot, wait) == 40
    slot, wait = slot_at(m, TEN_AM + 47)  # joined late, in its third slot
    assert seconds_left(m, long, slot, wait) == 13
