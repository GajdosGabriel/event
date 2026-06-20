---
name: migration-safety-laravel
description: "Use when: creating or editing Laravel migrations, adding columns, changing nullability, index changes, data backfills, and rollback-safe schema evolution. Keywords: migration, schema, column, nullable, index, rollback, database."
---

# Migration Safety Laravel

## Purpose
Pouzi tento skill, aby boli schema zmeny bezpecne pre lokalny vyvoj aj deployment pipeline.

## Workflow
1. Preferuj aditivne zmeny (najprv nove nullable stlpce).
2. Default hodnoty pridavaj len ked to dava semanticky zmysel.
3. Drz `down()` validny a symetricky s `up()`.
4. Ak je potrebna transformacia dat, urob explicitny backfill postup.
5. Vyhni sa rizikovym destruktivnym zmenam v rovnakej migracii ako feature rollout.

## Conventions
- Pouzivaj explicitne dlzky pre string stlpce tam, kde to projekt uz robi.
- Naming nech je jasny: styl `add_email_phone_to_x_table`.
- Nove stlpce umiestnuj cez `after()` len ked to zlepsuje citatelnost.
- `Schema::hasTable` alebo `Schema::hasColumn` guards pouzi len ked to vyzaduje styl projektu.

## Verification
- Spusti migrate z cisteho stavu, ak je to mozne.
- Spusti rollback danej migracie a over reversibilitu.
- Znovu spusti seed/tests, ak schema zmena ovplyvnuje factory alebo requesty.

## Done Checklist
- Nazov migracie a jej ucel sedia
- `up()` sa aplikuje bez chyby
- `down()` vrati zmenu korektne spat
- Suvisiace factory/validacie su aktualizovane
