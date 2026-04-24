# MyBike Import Module

PrestaShop 1.7 PHP modulis MyBike tiekėjo prekių duomenų paėmimui per REST API ir XML failų generavimui.

**Tikslas:** Generuoti du XML failus (pilną ir stock), kuriuos naudos atskiras PS importo modulis.

---

## API prieiga
- Base URL: `http://mybike.lt`
- Auth: `X-API-Key` header
- API raktas: `mbk_6ea0d5cbcd3fca5ee18ea4ffbdd9299d79014e7fd447c1dc11993188`
- Dokumentacija: `mybike-api-docs.md`

---

## Produktų kiekiai (~28,000 iš viso)
- **Bikes:** ~2,825 — MTB, Road, Gravel, Frameset, Urban, BMX, Triathlon, Kids (be sub-kategorijų)
- **E-Bikes:** ~1,000 — MTB, Urban, Road, Trekking (be sub-kategorijų)
- **Parts:** ~15,100 — su sub-kategorijomis (Drivetrain, Suspension, Brake, Tires, Wheels...)
- **Accessories:** ~9,400 — su sub-kategorijomis (Clothing, Tools, Watches, GPS...)

---

## Architektūra

### Stack
- PHP, shared hosting (Python neveikia kaip servisas)
- PrestaShop 1.7 modulis
- Cron per hosting scheduler (URL kvietimas su token)

### Failų struktūra
```
mybike_api_module/                          ← projekto katalogas (GitHub repo)
├── CLAUDE.md
├── mybike-api-docs.md
├── mybike-api.pdf
├── api_key.txt
└── mybike_xml_generator/                          ← PS modulis (kopijuoti į PS /modules/)
    ├── mybike_xml_generator.php                   # PS 1.7 modulio klasė
    ├── cron_full.php                       # Daily → products_full.xml
    ├── cron_stock.php                      # Hourly → products_stock.xml
    ├── config/config.php                   # Konstantos ir keliai
    ├── classes/
    │   ├── MyBikeApiClient.php             # cURL + retry
    │   ├── MyBikeLogger.php                # Failų logavimas
    │   ├── MyBikeFullSync.php              # Dviejų fazių sync logika
    │   ├── MyBikeStockSync.php             # Stock sync logika
    │   ├── MyBikeFullXmlBuilder.php        # XMLWriter → full XML
    │   └── MyBikeStockXmlBuilder.php       # XMLWriter → stock XML
    ├── controllers/admin/
    │   └── AdminMyBikeImportController.php
    ├── views/templates/admin/configure.tpl
    ├── output/                             # Generuojami XML (.gitignore)
    ├── logs/                               # Sync logai (.gitignore)
    └── .gitignore
```

### Sync strategija (~28k produktų — detail kvietimai neįmanomi visiems)

| Sync | Dažnis | Strategija | API kvietimai |
|------|--------|-----------|---------------|
| Full XML | 1x/parą | Tik `/api/v1/products` sąrašas (limit=100) | ~280 |
| Stock XML | 1x/valandą | Tas pats sąrašas | ~280 |
| Detalės + nuotraukos | Tik naujiems | `/products/{id}` + `/products/{id}/images` | N×2 (lazy) |

**Dviejų fazių pilnas sync:**
1. Fetch visi sąrašo puslapiai → palyginti su esamu XML
2. Nauji produktai → fetch details + images
3. Pasikeitęs `images_count` → fetch images
4. Kaina/stock pakitimas → atnaujinti iš sąrašo (be detail kvietimo)
5. Generuoti abu XML iš tų pačių duomenų

### XML formatai

**products_full.xml** — visi laukai:
`id, standard_item_id, manufacturer_id, brand, model, type, section, category, category_id, sub_category, price, base_price, color, size, description, specs (JSON CDATA), featured, availability{status,quantity,availability_date}, images[]{url,is_local}`

**products_stock.xml** — minimalus (greitas hourly update):
`id, price, base_price, availability{status,quantity,availability_date}`

### Cron URL'ai
```
https://sportomanai.lt/modules/mybike_xml_generator/cron_full.php?token=SECRET
https://sportomanai.lt/modules/mybike_xml_generator/cron_stock.php?token=SECRET
```

---

## Svarbūs API faktai
- Sąrašo endpointas turi visus reikalingus stock laukus — **nereikia detail kvietime stock XML**
- `specs` — laisvas JSON objektas, rašyti kaip CDATA
- `price` = dilerio kaina, `base_price` = MSRP — abi reikalingos
- Nuotraukos: tik išoriniai URL (nesiųsti lokaliai į PS)
- Bulk availability: iki 200 ID vienu kartu (optimizavimui)
