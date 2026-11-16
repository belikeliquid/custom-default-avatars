# Custom Default Avatars by Liquid Media

A WordPress plugin by [Liquid Media](https://belikeliquid.com).

Gives every commenter, user and subscriber without an avatar an image from your own set. Ships with 5 starter images (palm, sunset, wave, turtle, hibiscus).

## Features

- Random avatar from your image set for anyone without one
- Same person always gets the same image (based on their email)
- Add images from the Media Library, remove any image, or restore the 5 starters
- Becomes the site default avatar on activation (your old default comes back if you deactivate)
- Two modes:
  - **Keep real Gravatars**: people with a Gravatar keep it, everyone else gets an image from the set
  - **Skip Gravatar**: everyone gets an image from the set, served from your own site
- Plays nice with local avatar plugins: an uploaded avatar always wins

## Install

1. Download the repo as a ZIP (or clone it into `wp-content/plugins/custom-default-avatars`)
2. Activate **Custom Default Avatars by Liquid Media** under Plugins
3. Manage the images under **Settings > Avatar Randomizer**

## Notes

- Use square PNG or JPG images, 256px or bigger.
- Gravatar can only use PNG, JPG or GIF as a fallback. Other formats (like WebP) still work, but they replace real Gravatars for that image.
- Requires WordPress 5.6+ and PHP 7.4+.

## Credits

Developed by [Liquid Media](https://belikeliquid.com), Maui.

## License

GPL-2.0-or-later
