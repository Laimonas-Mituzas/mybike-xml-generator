# MyBike API Documentation

## Authentication
- Header: `X-API-Key: <your_key>`
- Base URL: `http://mybike.lt`
- All responses: JSON
- Errors 401 (invalid/missing key): `{"error": true, "message": "Invalid or expired API key."}`

## API Key (sportomanai.lt)
`mbk_6ea0d5cbcd3fca5ee18ea4ffbdd9299d79014e7fd447c1dc11993188`

---

## Endpoints

### GET /api/v1/products
List products (paginated, filterable)

**Query Parameters:**
| Param | Description |
|-------|-------------|
| page | Page number (default: 1) |
| limit | Items per page, max 100 (default: 50) |
| brand | Filter by exact brand name |
| category | Filter by category ID |
| section | Filter by section: `Bikes`, `E-Bikes`, `Parts`, `Accessories` |
| search | Search in brand and model names |
| in_stock | Set to `1` to show only available products |

**Response:**
```json
{
  "data": [
    {
      "id": 456,
      "standard_item_id": "SI-12345",
      "manufacturer_id": "MFR-789",
      "brand": "Orbea",
      "model": "Orca M30 2025",
      "type": "BIKES",
      "provider": "veloconnect",
      "price": 2499.99,
      "base_price": 2099.99,
      "section": "Bikes",
      "category": "Road Bikes",
      "category_id": 5,
      "availability": {
        "status": "available",
        "quantity": 3,
        "availability_date": null
      }
    }
  ],
  "meta": {
    "page": 1,
    "limit": 10,
    "total": 42,
    "pages": 5
  }
}
```

---

### GET /api/v1/products/{id}
Full product details

**Response fields (papildomai prie sąrašo):**
- `description` — teksto aprašymas
- `color` — spalva
- `size` — dydis
- `specs` — JSON objektas (pvz. `{"frame": "Carbon OMR", "groupset": "Shimano 105"}`)
- `featured` — boolean
- `sub_category` — subkategorija
- `images_count` — nuotraukų skaičius

```json
{
  "data": {
    "id": 456,
    "standard_item_id": "SI-12345",
    "manufacturer_id": "MFR-789",
    "brand": "Orbea",
    "model": "Orca M30 2025",
    "type": "BIKES",
    "provider": "veloconnect",
    "price": 2499.99,
    "base_price": 2499.99,
    "section": "Bikes",
    "category": "Road Bikes",
    "category_id": 5,
    "availability": { "status": "available", "quantity": 3, "availability_date": null },
    "description": "Lightweight carbon road bike...",
    "color": "Blue",
    "size": "M",
    "specs": {"frame": "Carbon OMR", "groupset": "Shimano 105"},
    "featured": false,
    "sub_category": "Race",
    "images_count": 4
  }
}
```

---

### GET /api/v1/products/{id}/availability
Single product stock status

```json
{
  "data": {
    "product_id": 456,
    "status": "available",
    "quantity": 3,
    "availability_date": null,
    "updated_at": "2026-02-19 08:00:00"
  }
}
```

Status values: `available` | `not_available` | `preorder`

---

### GET /api/v1/products/{id}/images
Product image URLs

```json
{
  "data": [
    { "id": 101, "url": "http://mybike.lt/product-photos/orbea-orca-m30-1.jpg", "is_local": true },
    { "id": 102, "url": "https://cdn.supplier.com/images/orbea-orca-m30-2.jpg", "is_local": false }
  ]
}
```

---

### GET /api/v1/products/availability/bulk
Bulk stock check (iki 200 produktų vienu kartu)

**Query Parameters:**
| Param | Description |
|-------|-------------|
| ids | Comma-separated product IDs, e.g. `ids=1,2,3` (max 200) |

```json
{
  "data": [
    { "product_id": 456, "status": "available", "quantity": 3, "availability_date": null, "updated_at": "..." },
    { "product_id": 789, "status": "not_available", "quantity": 0, "availability_date": "2026-03-15", "updated_at": "..." },
    { "product_id": 123, "status": "preorder", "quantity": 0, "availability_date": "2026-04-01", "updated_at": "..." }
  ]
}
```

---

### GET /api/v1/products/sections
Visos sekcijos

```json
{ "data": ["Accessories", "Bikes", "E-Bikes", "Parts"] }
```

---

### GET /api/v1/products/categories
Kategorijos su subkategorijomis

**Query Parameters:**
| Param | Description |
|-------|-------------|
| section | Optional: `Bikes`, `E-Bikes`, `Parts`, `Accessories` |

```json
{
  "data": [
    {
      "id": 3,
      "title": "Mountain Bikes",
      "section": "Bikes",
      "product_count": 85,
      "sub_categories": [
        {"id": 10, "title": "Cross Country"},
        {"id": 11, "title": "Trail"}
      ]
    }
  ]
}
```

---

### GET /api/v1/orders
Užsakymų sąrašas

**Query Parameters:**
| Param | Description |
|-------|-------------|
| page | default: 1 |
| limit | max 50, default: 20 |
| status | `created`, `in_progress`, `completed`, `cancelled` |

---

### GET /api/v1/orders/{id}
Pilna užsakymo informacija su eilutėmis (items)

---

### POST /api/v1/orders
Sukurti naują užsakymą

**Headers:** `Content-Type: application/json`, `X-API-Key`

**Body:**
```json
{
  "items": [
    {"product_id": 456, "quantity": 2},
    {"product_id": 789, "quantity": 1}
  ],
  "note": "Optional note"
}
```

**Response:** HTTP 201, grąžina sukurtą užsakymą su items.

**Error (400):**
```json
{ "error": true, "message": "No valid items", "details": ["Item 0: product 99999 not found"] }
```

---

## Pastabos modulio kūrimui

- Visų produktų paėmimui reikia iteruoti puslapius (`page`, `limit=100`)
- Nuotraukos gaunamos atskirai per `/products/{id}/images`
- Bulk availability endpoint leidžia efektyviai tikrinti atsargas (iki 200 ID vienu metu)
- `specs` laukas yra laisvas JSON — reikia apdoroti lankstiai
- `price` — kliento kaina, `base_price` — bazinė kaina
- Sekcijos: `Bikes`, `E-Bikes`, `Parts`, `Accessories`
