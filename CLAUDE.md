# MyBike XML Generator

PrestaShop 1.7 PHP modulis MyBike tiekėjo prekių duomenų paėmimui per REST API ir XML failų generavimui.

**Tikslas:** Generuoti du XML failus (pilną ir stock), kuriuos naudos atskiras PS importo modulis (būsimas projektas).

**GitHub:** https://github.com/Laimonas-Mituzas/mybike-xml-generator

---

## API prieiga
- Base URL: `http://mybike.lt`
- Auth: `X-API-Key` header
- API raktas: `api_key.txt` (neversijuojamas)
- Dokumentacija: `mybike-api-docs.md`

---

## Produktų kiekiai (~28,000 iš viso)
- **Bikes:** ~2,825 — MTB, Road, Gravel, Frameset, Urban, BMX, Triathlon, Kids (be sub-kategorijų)
- **E-Bikes:** ~1,000 — MTB, Urban, Road, Trekking (be sub-kategorijų)
- **Parts:** ~15,100 — su sub-kategorijomis (Drivetrain, Suspension, Brake, Tires, Wheels...)
- **Accessories:** ~9,400 — su sub-kategorijomis (Clothing, Tools, Watches, GPS...)

---

## Modulio informacija
- **Pavadinimas:** MyBike XML Generator
- **Modulio katalogas:** `mybike_xml_generator`
- **Klasė:** `Mybike_xml_generator`
- **Admin kontroleris:** `AdminMyBikeXmlGenerator`
- **Autorius:** Augu su Presta
- **PS versija:** 1.7

---

## Failų struktūra
```
mybike_api_module/                               ← GitHub repo šaknis
├── CLAUDE.md
├── mybike-api-docs.md
├── api_key.txt                                  # .gitignore — API raktas
├── mybike-api.pdf                               # .gitignore — API spec PDF
└── mybike_xml_generator/                        ← PS modulis → /modules/mybike_xml_generator/
    ├── mybike_xml_generator.php                 # PS 1.7 modulio klasė
    ├── logo.png                                 # Modulio logotipas
    ├── cron_full.php                            # Daily → products_full.xml
    ├── cron_stock.php                           # Hourly → products_stock.xml
    ├── config/config.php                        # Konstantos ir keliai
    ├── classes/
    │   ├── MyBikeApiClient.php                  # cURL + retry (3x)
    │   ├── MyBikeLogger.php                     # Failų logavimas su rotation
    │   ├── MyBikeFullSync.php                   # Dviejų fazių sync logika
    │   ├── MyBikeStockSync.php                  # Greitas stock sync
    │   ├── MyBikeFullXmlBuilder.php             # XMLWriter → products_full.xml
    │   └── MyBikeStockXmlBuilder.php            # XMLWriter → products_stock.xml
    ├── controllers/admin/
    │   └── AdminMyBikeXmlGeneratorController.php
    ├── views/templates/admin/configure.tpl      # Admin puslapis
    ├── output/                                  # .gitignore — generuojami XML
    ├── logs/                                    # .gitignore — sync logai
    └── .gitignore
```

---

## Sync strategija (~28k produktų)

| Sync | Dažnis | Strategija | API kvietimai |
|------|--------|-----------|---------------|
| Full XML | 1x/parą | Sąrašas (limit=100) + lazy details naujiems | ~280 + N×2 |
| Stock XML | 1x/valandą | Tik sąrašas (limit=100) | ~280 |

**Dviejų fazių pilnas sync:**
1. Fetch visi sąrašo puslapiai → palyginti su esamu `products_full.xml`
2. Nauji produktai → fetch `/products/{id}` + `/products/{id}/images`
3. Esami produktai → reuse cached duomenys iš XML
4. Abu XML generuojami iš tų pačių duomenų vieno run metu

---

## XML formatai

**products_full.xml:**
`id, standard_item_id, manufacturer_id, brand, model, type, provider, section, category, category_id, sub_category, price, base_price, color, size, description (CDATA), specs (JSON CDATA), featured, availability{status,quantity,availability_date}, images[]{url,is_local}`

**products_stock.xml:**
`id, price, base_price, availability{status,quantity,availability_date}`

---

## Admin puslapis
- API rakto konfigūracija
- Cron URL'ai (su token) — copy-paste į hosting scheduler
- XML failų būsena: dydis, paskutinio generavimo laikas, produktų kiekis, trukmė, statusas
- „Generuoti dabar" mygtukai abiem XML (tiesioginis paleidimas iš admin)
- Token regeneravimas

## Cron URL'ai (hosting scheduler)
```
https://sportomanai.lt/modules/mybike_xml_generator/cron_full.php?token=SECRET
https://sportomanai.lt/modules/mybike_xml_generator/cron_stock.php?token=SECRET
```

---

## Svarbūs API faktai
- Sąrašo endpointas turi visus stock laukus — **detail kvietimas stock XML nereikalingas**
- `specs` — laisvas JSON objektas, saugomas kaip CDATA
- `price` = dilerio kaina, `base_price` = MSRP — abi XML faile
- Nuotraukos: tik išoriniai URL (nesiųsti lokaliai)
- Sekcijos: `Bikes`, `E-Bikes`, `Parts`, `Accessories`
- Bikes/E-Bikes kategorijos **neturi** sub-kategorijų; Parts/Accessories **turi**
