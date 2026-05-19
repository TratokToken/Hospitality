/**
 * Tratok Hospitality API — Node.js client.
 *
 * Single-file SDK. Requires Node 18+ (uses the built-in global `fetch`).
 *
 *   const { TratokClient } = require('./tratok-client');
 *   const client = new TratokClient({ apiKey: process.env.TRATOK_API_KEY });
 *
 *   const me = await client.me();
 *   for await (const prop of client.iterProperties()) console.log(prop.id, prop.name);
 *   await client.setPrice({ roomId: 101, date: '2026-06-04', price: 219 });
 */

'use strict';

const crypto = require('crypto');

const DEFAULT_BASE_URL = 'https://hospitality.tratok.net/api.php';

class TratokAPIError extends Error {
  constructor(status, error, body) {
    super(`[${status}] ${error}`);
    this.name = 'TratokAPIError';
    this.status = status;
    this.error = error;
    this.body = body || {};
  }
}

class TratokRateLimitError extends TratokAPIError {
  constructor(status, error, retryAfter, body) {
    super(status, error, body);
    this.name = 'TratokRateLimitError';
    this.retryAfter = retryAfter;
  }
}

class TratokClient {
  constructor({
    apiKey,
    baseUrl = DEFAULT_BASE_URL,
    timeoutMs = 30000,
    autoRetry429 = true,
    maxRetries = 3,
  }) {
    if (!apiKey) throw new Error('apiKey is required');
    this.apiKey = apiKey;
    this.baseUrl = baseUrl;
    this.timeoutMs = timeoutMs;
    this.autoRetry429 = autoRetry429;
    this.maxRetries = maxRetries;
  }

  // ─── Low-level dispatcher ────────────────────────────────────────
  async _call(endpoint, { method = 'GET', params = {}, json = null } = {}) {
    const query = new URLSearchParams({ endpoint, ...params }).toString();
    const url = `${this.baseUrl}?${query}`;
    const init = {
      method,
      headers: {
        'X-API-Key': this.apiKey,
        'Accept': 'application/json',
        'User-Agent': 'tratok-node/2026.05',
      },
    };
    if (json !== null) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(json);
    }

    let attempt = 0;
    while (true) {
      const ctl = new AbortController();
      const tmo = setTimeout(() => ctl.abort(), this.timeoutMs);
      let response;
      try {
        response = await fetch(url, { ...init, signal: ctl.signal });
      } finally {
        clearTimeout(tmo);
      }

      if (response.status === 429 && this.autoRetry429 && attempt < this.maxRetries) {
        const wait = parseInt(response.headers.get('Retry-After') || '60', 10);
        await new Promise(r => setTimeout(r, wait * 1000));
        attempt++;
        continue;
      }

      let body;
      try { body = await response.json(); } catch { body = {}; }

      if (!response.ok || body.success !== true) {
        const errMsg = body.error || response.statusText;
        if (response.status === 429) {
          const wait = parseInt(response.headers.get('Retry-After') || '60', 10);
          throw new TratokRateLimitError(response.status, errMsg, wait, body);
        }
        throw new TratokAPIError(response.status, errMsg, body);
      }
      return body;
    }
  }

  // ─── Account ────────────────────────────────────────────────────
  async me() { return (await this._call('me')).data; }

  async rotateApiKey() {
    return (await this._call('api_key', { method: 'POST' })).data;
  }

  // ─── Properties ─────────────────────────────────────────────────
  async listProperties({ page = 1, limit = 20 } = {}) {
    return await this._call('properties', { params: { page, limit } });
  }

  async *iterProperties({ limit = 100 } = {}) {
    let page = 1;
    while (true) {
      const r = await this.listProperties({ page, limit });
      for (const item of r.data) yield item;
      if (page >= r.pagination.pages) return;
      page++;
    }
  }

  async getProperty(propertyId) {
    return (await this._call('properties', { params: { id: propertyId } })).data;
  }

  // ─── Rooms ──────────────────────────────────────────────────────
  async listRooms(propertyId) {
    return (await this._call('rooms', { params: { property_id: propertyId } })).data;
  }

  async getRoom(roomId) {
    return (await this._call('rooms', { params: { id: roomId } })).data;
  }

  // ─── Pricing ────────────────────────────────────────────────────
  async getPricing(roomId, { from, to } = {}) {
    const params = { room_id: roomId };
    if (from) params.from = from;
    if (to)   params.to   = to;
    return (await this._call('pricing', { params })).data;
  }

  async setPrice({ roomId, date, price }) {
    return (await this._call('pricing', {
      method: 'POST',
      json: { room_id: roomId, date, price },
    })).data;
  }

  async setPriceRange({ roomId, start, end, price }) {
    return (await this._call('pricing_bulk', {
      method: 'POST',
      json: { room_id: roomId, start_date: start, end_date: end, price },
    })).data;
  }

  // ─── Inventory ──────────────────────────────────────────────────
  async getInventory(roomId, { from, to } = {}) {
    const params = { room_id: roomId };
    if (from) params.from = from;
    if (to)   params.to   = to;
    return (await this._call('inventory', { params })).data;
  }

  async setInventory({ roomId, date, available }) {
    return (await this._call('inventory', {
      method: 'POST',
      json: { room_id: roomId, date, available },
    })).data;
  }

  async setInventoryRange({ roomId, start, end, available }) {
    return (await this._call('inventory_bulk', {
      method: 'POST',
      json: { room_id: roomId, start_date: start, end_date: end, available },
    })).data;
  }

  // ─── Activities + Restaurants ───────────────────────────────────
  async listActivities({ page = 1, limit = 20 } = {}) {
    return await this._call('activities', { params: { page, limit } });
  }
  async getActivity(id) {
    return (await this._call('activities', { params: { id } })).data;
  }
  async listRestaurants({ page = 1, limit = 20 } = {}) {
    return await this._call('restaurants', { params: { page, limit } });
  }
  async getRestaurant(id) {
    return (await this._call('restaurants', { params: { id } })).data;
  }

  // ─── Bookings ───────────────────────────────────────────────────
  async listBookings({ type, status, page = 1, limit = 20 } = {}) {
    const params = { page, limit };
    if (type)   params.type   = type;
    if (status) params.status = status;
    return await this._call('bookings', { params });
  }

  async *iterBookings(filters = {}) {
    let page = 1;
    const limit = filters.limit ?? 100;
    while (true) {
      const r = await this.listBookings({ ...filters, page, limit });
      for (const item of r.data) yield item;
      if (page >= r.pagination.pages) return;
      page++;
    }
  }

  async getBooking(id) {
    return (await this._call('bookings', { params: { id } })).data;
  }

  // ─── Revenue ────────────────────────────────────────────────────
  async revenue({ from, to, type } = {}) {
    const params = {};
    if (from) params.from = from;
    if (to)   params.to   = to;
    if (type) params.type = type;
    return (await this._call('revenue', { params })).data;
  }
}

/**
 * Verify an X-Webhook-Signature header against the raw POST body.
 * Pass the raw body as Buffer or string — DO NOT re-serialize JSON first.
 */
function verifyWebhook(secret, rawBody, signature) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(rawBody)
    .digest('hex');
  if (expected.length !== signature.length) return false;
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}

module.exports = {
  TratokClient,
  TratokAPIError,
  TratokRateLimitError,
  verifyWebhook,
};
