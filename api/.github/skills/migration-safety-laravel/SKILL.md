---
name: migration-safety-laravel
description: "Use when: creating or editing Laravel migrations, adding columns, changing nullability, index changes, data backfills, and rollback-safe schema evolution. Keywords: migration, schema, column, nullable, index, rollback, database."
---

# Migration Safety Laravel

## Pravidlá
- Aditívne zmeny: nový stĺpec najprv nullable, constraint až po backfille.
- Transformáciu dát rob ako explicitný backfill krok.
- Deštruktívne zmeny nedávaj do tej istej migrácie ako feature rollout.
- `down()` symetrický s `up()` a bez skrytej straty dát.

## Konvencie projektu
- Explicitné dĺžky string stĺpcov tam, kde to projekt už robí.
- Názov v štýle `add_email_phone_to_x_table`.
- `after()` a `Schema::hasTable/hasColumn` guardy len keď to zodpovedá okolitým migráciám.

## Overenie
- `migrate` a rollback danej migrácie; ak sa mení factory alebo request, spusti príslušné testy.
