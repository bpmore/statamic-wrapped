# What is bundled, and under what terms

Everything in this package that was not written for it is listed here, with
where it came from and what allows it to be here.

## The music (`resources/audio/*.m4a`)

Five instrumental tracks: Soft Landing, Open Road, Ipanema Morning, Kingston
Slow Sunday and Seoul Rooftop.

- Made with [Suno](https://suno.com) by Had A Farm, LLC on the Pro plan, a
  paid plan whose terms assign ownership of the generated output to the
  subscriber, including commercial use and distribution.
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

None on its own. Four things an editor or a reader does can cause one, and
one that looks as if it might does not:

- A logo given as a full `http(s)://` URL (config `theme.logo`, or the Logo
  field on the settings screen) is fetched once when the look is built, with a
  three-second timeout, and embedded in the images and video. Give a path on
  the site instead and nothing leaves the server.
- Choosing **A YouTube video** under Music when making a public link and
  pasting its address (1.5+) makes one request from the server to
  `https://www.youtube.com/oembed`, with a five-second timeout, carrying the
  video id and nothing else. It brings back the title and thumbnail URL, which
  are stored on the link. No API key is used and nothing about the site is sent.
- On a public page whose link has a song, a reader pressing **Play music** has
  their own browser load YouTube's player from `www.youtube-nocookie.com`
  (YouTube's privacy-enhanced domain, still operated by Google). That is a
  request from the reader to Google, on the same terms as any embedded YouTube
  video, and it does not happen until the button is pressed. A public page
  whose link has no song loads nothing from YouTube. The README says what
  this means for a site's privacy policy.
- A track chosen as the music on a public link (1.6+), bundled or your own,
  is streamed by your own site to the reader. Nothing leaves the server for
  it, and no one else is asked.
- The Chrome and FFmpeg binaries are run locally; they receive only the
  package's own HTML and files.

Beyond a video id and a logo URL you chose, nothing about a site or its
content is sent anywhere.
