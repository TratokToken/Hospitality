// Runnable demo. Node 18+ required (built-in fetch).
//
//   export TRATOK_API_KEY=…
//   node example-usage.js

const { TratokClient, TratokAPIError } = require('./tratok-client');

async function main() {
  if (!process.env.TRATOK_API_KEY) {
    console.error('Set TRATOK_API_KEY in your environment.');
    process.exit(1);
  }

  const client = new TratokClient({ apiKey: process.env.TRATOK_API_KEY });

  // 1. Connectivity check.
  const me = await client.me();
  console.log(`Connected as: ${me.name} (${me.email}) — ${me.company}`);
  console.log(`Rate limit: ${me.api_rate_limit} req/min\n`);

  // 2. List properties + rooms.
  console.log('Your properties:');
  for await (const prop of client.iterProperties()) {
    const approved = prop.approved ? '✓' : '·pending review';
    console.log(`  [${prop.id}] ${prop.name} (${prop.city}, ${prop.country}) ${approved}`);
    const rooms = await client.listRooms(prop.id);
    for (const room of rooms) {
      console.log(`      └ room ${String(room.id).padStart(4)}  ` +
                  `${room.name.padEnd(28)} ` +
                  `${room.currency} ${room.price}/night · ${room.numberOfRooms} units`);
    }
  }
  console.log();

  // 3. Recent confirmed bookings.
  console.log('Last 10 confirmed bookings (any type):');
  const recent = await client.listBookings({ status: 'confirmed', limit: 10 });
  for (const b of recent.data) {
    console.log(`  #${String(b.id).padEnd(6)} ${b.booking_type.padEnd(10)} ` +
                `${b.check_in} → ${b.check_out || '·'}  ` +
                `${b.guest_name.padEnd(25)} ${b.currency} ${b.total_price}  (${b.total_trat} TRAT)`);
  }
  console.log();

  // 4. Revenue last 30 days.
  const rev = await client.revenue();
  console.log(
    `Revenue ${rev.period.from} → ${rev.period.to}: ` +
    `USD ${rev.totals.gross_revenue.toFixed(2)} gross / ` +
    `USD ${rev.totals.net_revenue.toFixed(2)} net ` +
    `(${rev.totals.total_bookings} bookings)`
  );
}

main().catch(err => {
  if (err instanceof TratokAPIError) {
    console.error(`API error: ${err.message}`);
  } else {
    console.error(err);
  }
  process.exit(2);
});
