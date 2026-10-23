# Detekcia miesta (`venues/detect`)

## Endpoint
```
POST /api/dashboard/venues/detect
POST /api/admin/venues/detect
```

## Request
```json
{
  "name": "string, required, max 250",
  "city": "string, required, max 250",
  "country": "string|null, optional, max 100",
  "attach_image_to_model": "boolean, optional",
  "make_primary_image": "boolean, optional",
  "fileable_type": "string, required if attach_image_to_model=true; [canal|event|venue]",
  "fileable_id": "integer, required if attach_image_to_model=true; min 1"
}
```

## Response (Success - 200)
```json
{
  "success": true,
  "message": "Miesto analyzovane",
  
  "venue_payload": {
    "name": "string|null",
    "street": "string|null",
    "postcode": "string|null",
    "city": "string|null",
    "country": "string|null",
    "latitude": "number|null",
    "longitude": "number|null",
    "coordinates_source": "venue|address|ai|municipality|null",
    
    "object_description": "string|null (dlhší text, max 12 viet)",
    "long_description": "string|null (ešte dlhší text pre detail)",
    
    "image_url": "string|null (primárny obrázok)",
    "image_urls": [
      "string (url obrázka 1)",
      "string (url obrázka 2)",
      "..."
    ],
    
    "logo_url": "string|null (logo zo Wikidata P154)",
    "email": "string|null (email zo Wikidata)",
    "phone": "string|null (tel. číslo zo Wikidata)",
    
    "website": "string|null",
    "reference_url": "string|null (Wikipedia odkaz)",
    "enrichment_source": "wikipedia|null",
    
    "village_id": "integer|null"
  },
  
  "venue_store_payload": {
    "village_id": "integer|null",
    "name": "string|null",
    "street": "string|null",
    "postcode": "string|null",
    "body": "string|null",
    "website": "string|null",
    "country": "string|null",
    "latitude": "number|null",
    "longitude": "number|null",
    "coordinates_source": "venue|address|ai|municipality|null",
    "capacity": null,
    "opening_hours": null,
    "category": null,
    "status": "draft"
  },
  
  "missing_required_fields": ["field1", "field2"],
  "can_store_immediately": "boolean",
  
  "attached_files": [
    {
      "id": "integer",
      "type": "image",
      "is_primary": "boolean",
      "original_file_url": "string|null",
      "thumb_image_url": "string|null",
      "large_image_url": "string|null",
      "..."
    }
  ]
}
```

## Response (Error - 200 s success=false)
```json
{
  "success": false,
  "error": "text chyby"
}
```

## Zdroje obohatenia

Obrázky, popis, logo a kontakty dopĺňa
[WikipediaPlaceEnricher](../app/Services/Places/WikipediaPlaceEnricher.php)
z Wikipédie, Wikimedia a Wikidata (logo = P154) — bez platených API kľúčov,
s cache 24 h a fallbackom SK → EN. `image_url` je vždy prvý prvok `image_urls`;
`object_description` je skrátená verzia `long_description`. Pri chybe zdroja
vracia `null`, nie výnimku.

## Presnosť súradníc (`coordinates_source`)

Súradnice sa hľadajú rebríkom zdrojov, prvý úspešný vyhráva:

| Hodnota | Zdroj | Presnosť |
|---|---|---|
| `venue` | zhoda budovy v OpenStreetMap | budova |
| `address` | štruktúrovaný dotaz na adresu (ulica + PSČ + obec) | dom / ulica |
| `ai` | odhad modelu pre všeobecne známy objekt, overený voči obci (max 25 km) | približná |
| `municipality` | stred obce z geokódera | stred obce |
| `null` | nepodarilo sa určiť nič | — |

Miesto uložené cez formulár môže mať navyše `manual` — operátor značku posunul sám.
Hodnota sa ukladá do stĺpca `venues.coordinates_source`, aby sa presnosť nestratila
po uložení; UI podľa nej označí približné polohy.
