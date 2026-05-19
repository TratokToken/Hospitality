"""
Runnable demo of the Python client. Set TRATOK_API_KEY then:

    pip install -r requirements.txt
    python example_usage.py
"""
import os
import sys
from datetime import date, timedelta

from tratok_client import TratokClient, TratokAPIError


def main() -> int:
    api_key = os.environ.get("TRATOK_API_KEY")
    if not api_key:
        print("Set TRATOK_API_KEY in your environment.", file=sys.stderr)
        return 1

    client = TratokClient(api_key=api_key)

    # 1. Connectivity check.
    me = client.me()
    print(f"Connected as: {me['name']} ({me['email']}) — {me['company']}")
    print(f"Rate limit: {me['api_rate_limit']} req/min")
    print()

    # 2. List properties.
    print("Your properties:")
    for prop in client.iter_properties():
        approved = "✓" if prop["approved"] else "·pending review"
        print(f"  [{prop['id']}] {prop['name']} ({prop['city']}, {prop['country']}) {approved}")
        # 3. List rooms in each property.
        for room in client.list_rooms(prop["id"]):
            print(f"      └ room {room['id']:>4}  {room['name']:<28} "
                  f"{room['currency']} {room['price']}/night · "
                  f"{room['numberOfRooms']} units")
    print()

    # 4. Push pricing for the next 30 days on the first room found.
    try:
        first_prop = next(client.iter_properties())
        rooms = client.list_rooms(first_prop["id"])
        if rooms:
            room = rooms[0]
            today = date.today()
            in_30 = today + timedelta(days=30)
            print(f"Setting room {room['id']} to "
                  f"{room['currency']} 149 from {today} to {in_30}...")
            r = client.set_price_range(
                room_id=room["id"],
                start=today.isoformat(),
                end=in_30.isoformat(),
                price=149.00,
            )
            print(f"  Updated {r['days_updated']} days.")
    except StopIteration:
        print("(no properties to write against — skipping)")
    print()

    # 5. Recent confirmed bookings.
    print("Last 10 confirmed bookings (any type):")
    bookings = client.list_bookings(status="confirmed", limit=10)["data"]
    for b in bookings:
        print(f"  #{b['id']:<6} {b['booking_type']:<10} "
              f"{b['check_in']} → {b['check_out'] or '·'}  "
              f"{b['guest_name']:<25} "
              f"{b['currency']} {b['total_price']}  ({b['total_trat']} TRAT)")
    print()

    # 6. Revenue last 30 days.
    rev = client.revenue()
    print(f"Revenue {rev['period']['from']} → {rev['period']['to']}: "
          f"USD {rev['totals']['gross_revenue']:.2f} gross / "
          f"USD {rev['totals']['net_revenue']:.2f} net "
          f"({rev['totals']['total_bookings']} bookings)")

    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except TratokAPIError as e:
        print(f"API error: {e}", file=sys.stderr)
        sys.exit(2)
