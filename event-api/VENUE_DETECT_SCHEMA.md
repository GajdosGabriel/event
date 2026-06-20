# Enhanced Venue Detection Endpoint Schema

## Endpoint
```
POST /api/dashboard/venues/detect
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

## Nové funkcie (variant A+)

### 1. Multiple Images (`image_urls`)
- Pole obrázkov z Wikipedie/Wikimedia
- Primárny obrázok je vždy prvý (`image_url`)
- Frontend si môže vyberať alebo zobraziť galériu

### 2. Logo (`logo_url`)
- Logo z Wikidata (P154 property)
- Bezplatne, bez dodatočných API keyov
- Bergamónny pre mestá/mestnosti

### 3. Dlhší Text (`long_description`)
- Úplný text z Wikipedie (do 500 znakov, max 12 viet)
- `object_description` = skrátená verzia (5 viet) - backward compatible
- `long_description` = plná verzia pre detail view

### 4. Kontaktné Údaje
- `email`: Email adresa zo Wikidata (ak existuje)
- `phone`: Telefónne číslo zo Wikidata (ak existuje)
- Primárne pre mestá/kancelárické mestnosti

### 5. Optimalizácie backendu
- Caching: Všetky výsledky Wikipedie/Wikimedia sú cachované (24h)
- Performance: Paralelné requesty (Wikimedia + Wikidata súčasne)
- Fallback: Sk → En verzia Wikipedie
- Error handling: Bezpečné vracanie null miesto chýb

## Príklad použitia (frontend)

```typescript
// Request
const response = await fetch('/api/dashboard/venues/detect', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'Tatra',
    city: 'Poprad',
    country: 'Slovakia',
    attach_image_to_model: true,
    fileable_type: 'venue',
    fileable_id: 42
  })
});

const data = await response.json();

if (data.success) {
  // Obrázky
  console.log(data.venue_payload.logo_url);      // Logo
  console.log(data.venue_payload.image_urls[0]); // Prvý obrázok
  
  // Text
  console.log(data.venue_payload.long_description);
  
  // Kontakt
  console.log(data.venue_payload.email);
  console.log(data.venue_payload.phone);
  
  // Uložené súbory (keď attach_image_to_model=true)
  if (data.attached_files?.length) {
    console.log('Obrázok uložený:', data.attached_files[0].original_file_url);
  }
}
```

## Notes
- ✅ Backward compatible (stare polia `image_url`, `object_description` ďalej funčia)
- ✅ Bez platených API keyov (všetky zdroje bezplatné)
- ✅ Rýchlo (cache + optimálne requesty)
- ✅ Robustný (fallbacky a error handling)
