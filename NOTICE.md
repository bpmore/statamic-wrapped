# What is bundled, and under what terms

Everything in this package that was not written for it is listed here, with
where it came from and what allows it to be here.

## The music (`resources/audio/*.m4a`)

Five instrumental tracks: Soft Landing, Open Road, Ipanema Morning, Kingston
Slow Sunday and Seoul Rooftop.

- Made with [Suno](https://suno.com) by Had A Farm, LLC on a paid plan whose
  terms assign ownership of the generated output to the subscriber, including
  commercial use and distribution.
- Owned by Had A Farm, LLC and distributed with this package under the same
  MIT licence as the code.
- Shipped exactly as Suno delivered them, so Suno's own metadata (the
  "made with suno" tag) stays in each file. The test suite checks it is still
  there. Please leave it in place if you redistribute them.
- Not made with Suno's remix or cover features, which carry different terms.

The prompts they were written from are in `docs/suno-brief.md`.

## Third-party code in the built control panel assets (`public/build`)

- **canvas-confetti** by Kiril Vatev, ISC licence. Copyright (c) 2020, Kiril
  Vatev. Permission to use, copy, modify, and/or distribute this software for
  any purpose with or without fee is hereby granted, provided that the above
  copyright notice and this permission notice appear in all copies.
  https://github.com/catdad/canvas-confetti

The control panel components (`@statamic/cms`) are not bundled; they are
resolved from the host site's own Statamic installation at build time and
loaded from it at runtime.

## External requests this package can make

None on its own. Two settings can cause one:

- A logo given as a full `http(s)://` URL (config `theme.logo`, or the Logo
  field on the settings screen) is fetched once when the look is built, with a
  three-second timeout, and embedded in the images and video. Give a path on
  the site instead and nothing leaves the server.
- The Chrome and FFmpeg binaries are run locally; they receive only the
  package's own HTML and files.

Nothing about a site or its content is sent anywhere.
