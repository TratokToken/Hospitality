"""
Minimal Flask webhook receiver.

   pip install flask
   export TRATOK_WEBHOOK_SECRET=<the secret you saved on /webhooks.php>
   flask --app webhook_receiver run --port 8080

Then expose port 8080 publicly (ngrok, cloudflared, tailscale funnel)
and register that URL on https://hospitality.tratok.net/webhooks.php.
"""
from __future__ import annotations

import json
import os
import sys

from flask import Flask, request

from tratok_client import verify_webhook

SECRET = os.environ.get("TRATOK_WEBHOOK_SECRET", "")
if not SECRET:
    print("Set TRATOK_WEBHOOK_SECRET in your environment.", file=sys.stderr)
    sys.exit(1)

app = Flask(__name__)


@app.post("/tratok/webhook")
def receive() -> tuple[str, int]:
    sig = request.headers.get("X-Webhook-Signature", "")
    raw = request.get_data()                          # raw bytes BEFORE parsing
    if not verify_webhook(SECRET, raw, sig):
        return "bad signature", 401

    payload = json.loads(raw)
    event = payload.get("event")
    data = payload.get("data", {})

    # Dispatch on event type. Be tolerant of unknown events — the API
    # may introduce new ones; treat them as no-ops.
    if event == "booking.created":
        handle_booking_created(data)
    elif event == "booking.confirmed":
        handle_booking_confirmed(data)
    elif event == "booking.cancelled":
        handle_booking_cancelled(data)
    elif event == "payment.failed":
        handle_payment_failed(data)
    elif event in {"booking.modified", "review.created", "listing.updated", "payment.processed"}:
        # Acknowledge without action for this demo.
        pass

    return "", 204


def handle_booking_created(data: dict) -> None:
    print(f"[booking.created] #{data.get('booking_id')} "
          f"{data.get('booking_type')} status={data.get('status')}")


def handle_booking_confirmed(data: dict) -> None:
    print(f"[booking.confirmed] #{data.get('booking_id')} "
          f"{data.get('guest_name')} TRAT={data.get('total_trat')}")


def handle_booking_cancelled(data: dict) -> None:
    print(f"[booking.cancelled] #{data.get('booking_id')} "
          f"refund_trat={data.get('refund_trat')}")


def handle_payment_failed(data: dict) -> None:
    print(f"[payment.failed] #{data.get('booking_id')} "
          f"expired_at={data.get('expired_at')}")


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8080)
