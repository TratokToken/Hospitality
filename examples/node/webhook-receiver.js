// Minimal Express webhook receiver.
//
//   npm install express
//   export TRATOK_WEBHOOK_SECRET=…
//   node webhook-receiver.js
//
// Then expose port 8080 publicly (ngrok, cloudflared, tailscale funnel)
// and register that URL on https://hospitality.tratok.net/webhooks.php.

const express = require('express');
const { verifyWebhook } = require('./tratok-client');

const SECRET = process.env.TRATOK_WEBHOOK_SECRET;
if (!SECRET) {
  console.error('Set TRATOK_WEBHOOK_SECRET in your environment.');
  process.exit(1);
}

const app = express();
// CRITICAL: use raw body for signature verification — express.json()
// re-serializes and breaks the HMAC. We keep the raw bytes around.
app.use('/tratok/webhook', express.raw({ type: 'application/json' }));

app.post('/tratok/webhook', (req, res) => {
  const sig = req.header('X-Webhook-Signature') || '';
  if (!verifyWebhook(SECRET, req.body, sig)) {
    return res.status(401).send('bad signature');
  }

  const payload = JSON.parse(req.body.toString('utf8'));
  switch (payload.event) {
    case 'booking.created':   onBookingCreated(payload.data);   break;
    case 'booking.confirmed': onBookingConfirmed(payload.data); break;
    case 'booking.cancelled': onBookingCancelled(payload.data); break;
    case 'payment.failed':    onPaymentFailed(payload.data);    break;
    default: /* unknown event — ack and ignore */ break;
  }
  res.status(204).end();
});

function onBookingCreated(d) {
  console.log(`[booking.created] #${d.booking_id} ${d.booking_type} status=${d.status}`);
}
function onBookingConfirmed(d) {
  console.log(`[booking.confirmed] #${d.booking_id} ${d.guest_name} TRAT=${d.total_trat}`);
}
function onBookingCancelled(d) {
  console.log(`[booking.cancelled] #${d.booking_id} refund_trat=${d.refund_trat}`);
}
function onPaymentFailed(d) {
  console.log(`[payment.failed] #${d.booking_id} expired_at=${d.expired_at}`);
}

const port = parseInt(process.env.PORT || '8080', 10);
app.listen(port, () => console.log(`Listening on :${port}`));
