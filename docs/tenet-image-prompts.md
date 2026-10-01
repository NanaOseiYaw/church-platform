# Image prompts — The Eleven Tenets

Generation prompts for the illustrations on `/about/beliefs`.

**Read this first.** Consistency matters more than any individual image. Eleven
images generated with eleven different moods will look like a collage; eleven
generated from one style system will look designed. So:

1. The **STYLE BLOCK** below is prepended, unchanged, to every single prompt.
2. Only the **SUBJECT** line changes per tenet.
3. Generate all eleven in one session if the tool allows it, so the model keeps
   its stylistic footing.

If one image comes out off-style, regenerate that one rather than adjusting the
style block — changing the block means regenerating all eleven.

---

## Two hard constraints

**No depiction of Jesus, God, or the Holy Spirit as a figure.** This matters
theologically for a Pentecostal assembly, and separately, generated faces of
religious figures look uncanny and will date badly. Symbol and light only.

**No text, letterforms, or numerals anywhere in the image.** Generators render
text poorly, and every one of these sits next to a real heading that already
says what it is.

---

## STYLE BLOCK

> Prepend verbatim to each prompt below.

```
Minimal symbolic illustration in a modern editorial style. Flat, geometric,
vector-like forms given gentle volume by soft directional light. Strictly
limited palette: deep navy blue (#1E5AA8) and light sky blue (#5AA9E6) as the
primary colours, with warm gold (#F2C94C) used sparingly as a single accent,
all on a very light cool off-white background (#F5F8FC). Generous negative
space, centred composition, calm and reverent. Fine subtle grain, no harsh
shadows, no outlines. The feel of a thoughtful software company's illustration
system — restrained and premium, not clipart, not stock photography, not
cartoon, not 3D render. Absolutely no text, letters, numbers or symbols of
language. No human faces and no depiction of any divine figure.
3:2 landscape aspect ratio.
```

---

## The eleven subjects

### 01 — The Bible
```
SUBJECT: An open book seen from a low three-quarter angle, its pages fanning
upward and dissolving into thin geometric rays of light that rise and widen
above it. The light is the focal point; the book is simple and unadorned.
```

### 02 — One True God
```
SUBJECT: Three slender interlocking geometric arcs forming a single continuous
unbroken loop, with one warm light source at the centre illuminating all three
equally. Perfectly balanced, nothing dominant. Pure geometry, no figures.
```

### 03 — The Depraved Nature of Humanity
```
SUBJECT: A single smooth stone form, cracked through the middle, with one fine
seam of warm gold light running along the fracture. Sombre but not bleak — the
gold seam is the only warmth and it reads as hope entering the break.
```

### 04 — The Saviour
```
SUBJECT: A simple empty cross in silhouette standing at the horizon, with dawn
light breaking behind it and spreading outward in soft concentric bands. The
cross is bare and unoccupied. Quiet, spacious, early-morning stillness.
```

### 05 — Repentance, Justification & Sanctification
```
SUBJECT: A path curving from shadowed foreground toward a bright horizon,
the ground transitioning from deep navy to pale light along its length. A
single journey, one direction, dawn ahead.
```

### 06 — The Ordinances of Baptism and the Lord's Supper
```
SUBJECT: Still water seen from directly above with clean concentric ripple
rings expanding from a single point, and resting at the edge of the frame a
simple cup and a single piece of bread rendered as minimal geometric forms.
```

### 07 — Baptism, Gifts & Fruit of the Holy Spirit
```
SUBJECT: A single flame hovering above, its light branching downward into
seven fine radiating lines that spread and soften as they descend. Weightless,
upward-moving energy. The flame is small; the radiating light is the subject.
```

### 08 — Divine Healing
```
SUBJECT: Two open cupped hands seen from the side, palms upward, with soft
warm light gathering in the space between them. Hands only — no face, no body,
no arms beyond the wrist. Gentle and still.
```

### 09 — Tithes & Offerings
```
SUBJECT: An open upturned palm releasing small round forms that rise upward
and turn into motes of warm light as they ascend. Giving shown as release
rather than as wealth — no coins, no currency, no money symbols.
```

### 10 — The Second Coming & the Next Life
```
SUBJECT: A wide horizon at the moment of daybreak, layered cloud bands parting
to reveal an intense source of light behind them, long rays reaching across the
whole frame. Vast scale, anticipation, nothing ominous.
```

### 11 — Marriage & Family Life
```
SUBJECT: Two interlocking arcs forming a sheltering canopy, with three small
simple rounded forms resting beneath them. Abstract geometry suggesting
covering and protection — no faces, no figures, no rings.
```

---

## Output settings

| Setting | Value |
|---|---|
| Aspect ratio | **3:2 landscape** |
| Size | 1536 × 1024 or larger |
| Format | PNG from the generator, converted to **WebP** before committing |
| Background | Light — must sit on a white page without a visible box edge |

## File naming

Save into `public/images/tenets/` using exactly these names, because the page
looks them up by filename:

```
01-the-bible.webp
02-one-true-god.webp
03-depraved-nature.webp
04-the-saviour.webp
05-repentance.webp
06-ordinances.webp
07-holy-spirit.webp
08-divine-healing.webp
09-tithes-offerings.webp
10-second-coming.webp
11-marriage-family.webp
```

Compress before committing — these go in the git repo and are served on every
visit to the page. Target **under 150 KB each**. Anything over ~400 KB will be
noticeable on a phone connection.

## If an image is missing

The page falls back to the existing Lucide icon for that tenet. Nothing breaks,
and you can add the images one at a time rather than all at once.
