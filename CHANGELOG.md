# Changelog

## 3.0.0 (unreleased)

### Breaking changes

- Requires utopia-php/database 8. Pass a database 8 `Utopia\Database\Database` to `TimeLimit\Database`; `getLogs()` returns database 8 `Document`s.
- Requires PHP 8.5 or later, the floor utopia-php/database 8 sets. abuse 2.x declared PHP 8.4.1 but could not be installed on PHP 8.4 either, because every utopia-php/database 7 release requires PHP 8.5.
- `TimeLimit\Database::ATTRIBUTES` and `TimeLimit\Database::INDEXES` are removed. `TimeLimit\Database::attributes()` and `TimeLimit\Database::indexes()` return the same schema as `Utopia\Database\Attribute` and `Utopia\Database\Index` objects, and `setup()` creates the collection from them. The schema is unchanged, so an existing `abuse` collection needs no migration.
