# Правила лоадеров

> Правила для loader-pipeline. Загружаются после `rules/base.md`.

## Правила

- Оставляй `LoaderInterface` тонким: только `load(Module): void` и `priority(): int`; не добавляй ключи, имена или whitelist-контракты манифеста.
- Каждый лоадер должен быть идемпотентным и делать ранний return из `load()`, если ожидаемых файлов или директорий модуля нет.
- Резолви пути модуля через `ModuleLayout`; не хардкодь подпути модулей внутри лоадеров или генераторов.
- Запускай один упорядоченный pipeline по `ModuleRegistryInterface::all()` и лоадерам, отсортированным по `priority()`, пропуская отключённые модули.
- При добавлении лоадеров сохраняй смысл приоритетов: config и providers первыми, ресурсы до routes, commands после routes.
- MoonShine autoload не является `LoaderInterface`; держи его отдельным optional bridge через `MoonShineModuleAutoloader`.
- Регистрируй кастомные лоадеры через DI/service-provider configuration, а не через флаги манифеста.
