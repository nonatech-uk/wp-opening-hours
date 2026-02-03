# Opening Hours

A WordPress plugin that displays business opening hours from the Google Places API, with live open/closed status.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- A Google Places API key

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/nonatech-uk/opening-hours/releases).
2. Upload the `opening-hours` folder to `wp-content/plugins/`.
3. Activate the plugin in **Plugins > Installed Plugins**.

## Configuration

Go to **Settings > Opening Hours** and fill in:

- **Google API Key** -- Your Google Places API key ([get one here](https://developers.google.com/maps/documentation/places/web-service/get-api-key)).
- **Google Place ID** -- The Place ID for your business ([find yours here](https://developers.google.com/maps/documentation/places/web-service/place-id)).
- **Cache Duration** -- How long to cache the API response, in hours (default: 24).

## Shortcode

Use `[opening_hours]` anywhere in your posts, pages, or widgets.

An optional `class` attribute lets you add custom CSS classes:

```
[opening_hours class="my-custom-class"]
```

The shortcode outputs a status indicator showing whether the business is currently open or closed.

## Auto-Updates

The plugin checks GitHub releases for new versions automatically. When an update is available it will appear in the standard WordPress **Dashboard > Updates** screen -- no manual download required.

## License

This project is licensed under the [Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)](LICENSE) license. You are free to use and modify it for non-commercial purposes with attribution.
