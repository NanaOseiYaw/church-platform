# Image prompts — The Twelve Core Values

Generation prompts for the illustrations on `/about/core-values`.

**These are not the same kind of image as the tenets.** Read the next section
before generating anything, or you will produce twelve beautiful pictures that
turn to mush on the page.

---

## Why the style block is different from the tenets

The tenet illustrations sit in a wide banner across the top of each card and
render around 400 pixels wide. They can afford fine gradients, soft grain and
generous negative space.

These do not. Each one renders in a **square tile 80 pixels across**, beside the
heading, where the icon currently sits. At that size:

- negative space is wasted — the shape must fill the tile,
- fine gradients and grain disappear or turn to noise,
- more than two or three shapes becomes an unreadable smudge.

So this style block asks for **bolder, simpler, flatter** work than the tenet
one. Same palette, same family, different weight. Do not reuse the tenet style
block here.

The square tile is also a deliberate design choice rather than a technical one:
giving these twelve a banner each, like the tenets have, would double the length
of an already text-heavy page and make the two pages look like the same page
twice.

---

## Two hard constraints

Unchanged from the tenets, and they still matter:

**No depiction of Jesus, God, or the Holy Spirit as a figure.** Symbol and light
only.

**No text, letterforms, or numerals anywhere in the image.** Every tile sits
beside a real heading that already says what it is.

---

## A third constraint: no two subjects may share a base form

Each of these renders at 80 px beside its own heading, in a grid where cards sit
directly next to each other. Two subjects built on the same underlying shape —
say, two circles with a star inside — become the same picture at that size, and
the pair reads as a mistake even though each one is fine on its own.

Before generating, read the twelve subjects below as a set and check that no two
start from the same form. If a rewrite is needed, change the one whose symbol is
the weaker fit, not the stronger one.

---

## What went wrong on the first pass

Three of the original twelve had to be regenerated, and all three failures came
from the prompt rather than the generator. They are worth knowing because the
same traps apply to any new subject added later:

- **Church Discipline** asked for a balance scale with "a navy upright and a sky
  blue crossbeam". A vertical post crossed by a horizontal beam is a crucifix.
  The image read as a cross, which is both wrong for the value and badly placed
  theologically. Now specified as a hanging balance with no upright at all.
- **Holiness** asked for a gold star on a navy disc while **Leadership** asked
  for a compass rose — a star on a navy disc. Side by side in the grid, at
  05 and 06, they were the same tile twice. Holiness was rewritten; the compass
  rose was kept, because it is the stronger symbol of the two.
- **Discipleship** asked for "rounded stepping stones rising in a staircase",
  which the generator read as an invitation to draw three-dimensional blocks in
  perspective. The style block now forbids perspective outright, and the subject
  asks for flat footprints seen from above.

The pattern: describe the shape precisely enough that a wrong reading is not
available, and say what you do **not** want when a near-miss is plausible.

---

## STYLE BLOCK

> Prepend verbatim to each subject below.

```
Minimal symbolic icon-illustration in a modern editorial style, designed to be
read clearly at small size. Bold, flat, geometric vector shapes with crisp
edges and only the gentlest suggestion of volume. Two or three shapes maximum,
filling most of the square frame — confident and graphic, not delicate.
Strictly limited palette: deep navy blue (#1E5AA8) and light sky blue (#5AA9E6)
as the primary colours, with warm gold (#F2C94C) as a single small accent, all
on a very light cool off-white background (#F5F8FC). Even lighting, no grain,
no outlines, no drop shadows, no fine detail or thin lines. Strictly flat and
two-dimensional: no perspective, no isometric or three-quarter views, no
extruded or boxy depth, no cast shadows — every shape is seen square on. The
feel of a premium app icon set — restrained and modern, not clipart, not stock
photography, not cartoon, not 3D render. Absolutely no text, letters, numbers
or symbols of language. No human faces and no depiction of any divine figure.
1:1 square aspect ratio, subject centred with a small even margin.
```

---

## The twelve subjects

### 01 — Evangelism
```
SUBJECT: A simple solid cone shape angled upward to the right, with three clean
concentric arcs widening away from its mouth. The outermost arc is warm gold.
Bold and graphic, like a signal going out.
```

### 02 — Discipleship
```
SUBJECT: Three large footprint shapes in a diagonal line rising towards the
upper right, drawn as simple solid silhouettes with no toe detail, seen from
directly above. The first deep navy, the second sky blue, the third warm gold.
Completely flat — no boxes, no blocks, no perspective, no shading.
```

### 03 — The Holy Spirit
```
SUBJECT: A single bold flame shape, deep navy at the base rising through sky
blue, with a solid warm gold core at its heart. One form only, filling the
frame.
```

### 04 — Ministry Excellence
```
SUBJECT: A faceted gem seen head on, cut into a few large clean planes of navy
and sky blue, with one single facet in warm gold catching the light. Solid and
weighty, not sparkling or detailed.
```

### 05 — Leadership
```
SUBJECT: A compass rose — a solid navy circle with a bold four-pointed needle
across it, the northward point in warm gold. Geometric and symmetrical.
```

### 06 — Holiness
```
SUBJECT: A solid deep navy rectangular block seen square on, with one clean
horizontal segment lifted clear of it and floating just above, that lifted
segment in warm gold with a sky blue edge. Set apart — literally separated out
and raised. Two shapes only, flat, no perspective.
```

### 07 — Consistent Bible Teaching
```
SUBJECT: A closed book seen flat from directly above, a simple navy rectangle
with a sky blue spine down one side and a single warm gold ribbon marker
emerging from the lower edge. Flat and graphic — no pages, no perspective.
```

### 08 — Church Discipline
```
SUBJECT: A hanging balance, perfectly level — a thick horizontal sky blue beam
suspended from a small warm gold ring at the top, with a shallow deep navy pan
joined to each end of the beam by a short thick stem. There is NO vertical post,
upright, stand or column of any kind. Bold shapes, no chains, no thin lines.
```

### 09 — Tithes & Offerings
```
SUBJECT: Three solid discs stacked slightly offset inside a simple shallow
bowl, the bowl in deep navy, the discs in sky blue with the topmost one in warm
gold. No hands, no coins with detail — pure shapes.
```

### 10 — Social Responsibility
```
SUBJECT: Two broad interlocking arcs, one navy and one sky blue, curving
together to form a single heart shape, with the area where they overlap in warm
gold. Two becoming one.
```

### 11 — Church Culture
```
SUBJECT: A simple church building seen head on — a solid navy block with a tall
sky blue roofline rising to a point, and one warm gold arched doorway at its
centre. Flat elevation, no windows, no detail.
```

### 12 — Core Practices
```
SUBJECT: Two tall rounded forms leaning in to meet at the top, abstracted hands
raised in prayer, navy on one side and sky blue on the other, with a soft warm
gold glow in the narrow space between them. Abstract shapes only, no fingers
and no faces.
```

---

## Output settings

| Setting | Value |
|---|---|
| Aspect ratio | **1:1 square** |
| Size | 1024 × 1024 |
| Format | PNG from the generator, converted to **WebP** before committing |
| Background | Light — must sit on a white card without a visible box edge |

Because these render at 80 px, they compress very small. Target **under 25 KB
each**; anything over 60 KB means the image has more detail than the tile can
show, which is a sign to simplify rather than to compress harder.

## Checking them before you commit

Shrink each one to 80 × 80 and look at it there, not at full size. If you cannot
tell at a glance which value it belongs to, the shapes are too fine — regenerate
with fewer, larger elements rather than accepting it.

## File naming

Save into `public/images/core-values/` using exactly these names, because the
page looks them up by filename:

```
01-evangelism.webp
02-discipleship.webp
03-holy-spirit.webp
04-ministry-excellence.webp
05-leadership.webp
06-holiness.webp
07-bible-teaching.webp
08-church-discipline.webp
09-tithes-offerings.webp
10-social-responsibility.webp
11-church-culture.webp
12-core-practices.webp
```

## If an image is missing

The card falls back to its existing Lucide icon in the smaller tile. Nothing
breaks, and they can be added one at a time rather than all twelve at once.
