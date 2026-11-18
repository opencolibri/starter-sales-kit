# opencolibri/starter-sales-kit

> PoC: the sales kit admin (shops, datasets, castor-api runs) as a Symfony bundle, on
> [Sylius Shadcn Admin UI](https://github.com/Sylius/ShadcnAdminUi). Each team builds its own sales kit instance
> from it: users, branding, colors, domains and extra pages stay in the instance.

Developed in the [colibri-starter](https://github.com/opencolibri/colibri-starter) monorepo (`src/SalesKit`), split
into the read-only [opencolibri/starter-sales-kit](https://github.com/opencolibri/starter-sales-kit) repository. Unlike
the other packages, it is not a Castor plugin but a Symfony bundle: it requires `opencolibri/starter-core` like every
package of the monorepo, and drives the import tasks of `opencolibri/starter-import` through castor-api.

## What the package ships

| Part | Where |
|---|---|
| castor-api client, shop images and `project.yaml` storage, run progress | `src/Castor/`, `src/Import/` |
| Pages: dashboard figures, shops, datasets, Caddy on-demand TLS endpoint, theme thumbnails (from opencolibri/starter-themes) | `src/Controller/`, `templates/` |
| Live components (`SalesKit:RunMonitor`, `ShopActions`, `ShopVisibility`, `ThemePicker`, `DeleteShop`) | `src/Twig/Components/` |
| Sidebar items (Dashboard, Shops, Datasets) | `src/Menu/AdminMenuBuilder.php` (decorates `sylius_admin_ui.knp.menu_builder`) |
| Hooks of every page, plus the Shadcn Admin UI hooks | `config/app.php` |
| Tailwind sources of the templates | `assets/styles/sales-kit.css` |

Login, logout, the layout, the sidebar, the dark mode and the color themes come from Sylius Admin UI and Shadcn Admin UI.

## Install it in an instance

`sylius/shadcn-admin-ui` has no release yet: allow its development version next to the package.

```json
"require": {
    "opencolibri/starter-sales-kit": "^0.1@dev",
    "sylius/shadcn-admin-ui": "^0.14@dev"
}
```

1. Enable the bundles: Sylius Admin UI, Shadcn Admin UI, Live Components,
   then `OpenColibri\Starter\SalesKit\OpenColibriStarterSalesKitBundle`.
2. Import the hooks, then add your own below the import (they win):
   ```yaml
   # config/packages/opencolibri_sales_kit.yaml
   imports:
       - { resource: '@OpenColibriStarterSalesKitBundle/config/app.php' }
   ```
3. Import the routes:
   ```yaml
   sylius_admin_ui:
       resource: '@SyliusAdminUiBundle/config/routes.php'
   opencolibri_sales_kit:
       resource: '@OpenColibriStarterSalesKitBundle/config/routes.php'
   live_component:
       resource: '@LiveComponentBundle/config/routes.php'
       prefix: '/_components'
   ```
4. Stylesheet `assets/styles/admin.css`, listed in `symfonycasts_tailwind.input_css`:
   ```css
   @import "tailwindcss";
   @import "../../vendor/sylius/shadcn-admin-ui/assets/styles/theme.css";
   @import "../../vendor/opencolibri/starter-sales-kit/assets/styles/sales-kit.css";
   ```
5. A firewall on the Sylius Admin UI login routes, with `/_caddy/on-demand-ask` and `/login` public.

## Configuration

Every option defaults to an environment variable:

```yaml
opencolibri_sales_kit:
    castor_api:
        url: '%env(CASTOR_API_URL)%'
        token: '%env(default::CASTOR_API_TOKEN)%'
    shop_root_domain: '%env(SHOP_ROOT_DOMAIN)%'      # shops on {slug}.<domain>
    import_var_dir: '%env(IMPORT_VAR_DIR)%'          # .castor/import/var shared with castor-api
    import_export_dir: '%env(IMPORT_EXPORT_DIR)%'    # dataset zips
```

## Customizing an instance

- **Branding:** `sylius_admin.common.component.sidebar.logo`, `sylius_admin.security.login.page.logo` and
  `sylius_admin.base#base_title` hooks.
- **Colors:** `sylius_shadcn_admin_ui.themes` (e.g. `[stone, rose]`), or shadcn tokens in `admin.css`.
- **Blocks on a page:** each page renders its own hook plus `opencolibri_sales_kit.common.page`. Add a hookable to
  `opencolibri_sales_kit.shop.show.content.body.aside` (or `.main`, `.content.body` of any page), disable one with
  `enabled: false`, or replace its template. The page data is in `hookable_metadata.context` (`shop`, `shops`,
  `datasets`, `form`…).
- **Pages:** controllers of the instance, and a decorator of `sylius_admin_ui.knp.menu_builder` for the sidebar.

- **Grids:** `ShopGrid` and `DatasetGrid` (`src/Grid/`) are PHP Sylius grids (`#[AsGrid]`), so an instance changes
  them like any Sylius grid. castor-api returns every shop or dataset in one call: the grid providers turn the criteria,
  sorting and page into calls on `ShopRepository` / `DatasetRepository` (immutable in-memory queries:
  `withSearch()`, `withTheme()`, `orderBy()`, `paginate()`…), loaded once per request.

| Page hook | Context |
|---|---|
| `opencolibri_sales_kit.shop.index` | `resources` (grid view) |
| `opencolibri_sales_kit.shop.new` | `form`, `datasets` |
| `opencolibri_sales_kit.shop.show` | `shop`, `projectForm`, `themes`, `theme_headers` |
| `opencolibri_sales_kit.dataset.index` | `resources` (grid view) |
| `opencolibri_sales_kit.dataset.new` | `form`, `source` |
| `sylius_admin.dashboard.index` | — |

## Notes from the PoC

- Inside a `<twig:…>` component, `this` is that component and macros imported by the template are not visible: read
  the data and render the hooks before it. Shared bits are anonymous components (`<twig:sales_kit:action_button>`,
  `<twig:sales_kit:message>`).
- Never `{% set icon = ux_icon(...) %}` then `{{ icon }}`: `ux_icon()` returns a plain string and is only marked safe where
  it is printed, so the variable is escaped. Print it directly, or capture it with `{% set icon %}…{% endset %}`
  (a `Markup`, safe everywhere, including inside the blocks of another component).
- Not done yet: the castor-side tasks (`castor/*.php` of the sales kit repo) still live in the instance repo, no tests,
  English only.
