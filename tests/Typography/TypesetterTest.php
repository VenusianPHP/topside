<?php

declare(strict_types=1);

use Surface\Contracts\Typography\Font;
use Surface\Contracts\Typography\FontRequest;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\GlyphRun;
use Surface\Contracts\Typography\TextAlign;
use Surface\Contracts\Typography\TypesetLine;
use Surface\Contracts\Typography\TypographyException;

/*
 * The typesetter lays text out with whatever drivers are registered. These faces are 1000 units an
 * em and every glyph 500 wide, so at size 10 a character is 5 across, and a line is 10 tall (ascent
 * 8, descent 2) in the main face.
 */

function mainFace(array $offsets = []): FakeFace
{
    return new FakeFace(new FontSource('main.ttf'), range(0x20, 0x7E), offsets: $offsets);
}

function font10(): Font
{
    return new Font(new FontRequest(), 10.0);
}

/** @return list<array{float, float, float, float, float}> each line's top, baseline, height, left, width */
function lineBoxes(array $lines): array
{
    return array_map(fn (TypesetLine $line) => [$line->top, $line->baseline, $line->height, $line->left, $line->width], $lines);
}

/** @return list<string> each line's characters, read back from its glyphs (numbered as their codepoints) */
function lineText(array $lines): array
{
    return array_map(fn (TypesetLine $line) => implode('', array_map(
        fn (GlyphRun $run) => implode('', array_map('mb_chr', $run->glyphs)),
        $line->runs,
    )), $lines);
}

it('ends lines at newlines, drops carriage returns, and stacks each line under the one before', function (): void {
    $text = fakeTypesetter(mainFace())->set("ab\r\ncd\n", font10());

    expect(lineText($text->lines))->toBe(['ab', 'cd', ''])
        ->and(lineBoxes($text->lines))->toEqual([[0.0, 8.0, 10.0, 0.0, 10.0], [10.0, 18.0, 10.0, 0.0, 10.0], [20.0, 28.0, 10.0, 0.0, 0.0]])
        ->and([$text->width, $text->height])->toEqual([10.0, 30.0]);
});

it('breaks a line after spaces where the next word would pass the width, not counting the spaces that end it', function (): void {
    $typesetter = fakeTypesetter(mainFace());
    $narrow = $typesetter->set('aa bb cc', font10(), 22.0);
    $exact = $typesetter->set('aa bb cc', font10(), 25.0);

    expect(lineText($narrow->lines))->toBe(['aa ', 'bb ', 'cc'])
        ->and(array_column(lineBoxes($narrow->lines), 4))->toEqual([10.0, 10.0, 10.0])
        ->and(lineText($exact->lines))->toBe(['aa bb ', 'cc'])
        ->and(array_column(lineBoxes($exact->lines), 4))->toEqual([25.0, 10.0]);
});

it('keeps a word wider than the width on a line of its own, first or not', function (): void {
    $typesetter = fakeTypesetter(mainFace());

    expect(lineText($typesetter->set('a bbbbbbbb c', font10(), 20.0)->lines))->toBe(['a ', 'bbbbbbbb ', 'c'])
        ->and(lineText($typesetter->set('bbbbbbbb a c', font10(), 20.0)->lines))->toBe(['bbbbbbbb ', 'a c'])
        ->and($typesetter->set('bbbbbbbb a c', font10(), 20.0)->width)->toBe(40.0);
});

it('aligns each line across the width, or across the widest line when there is none', function (): void {
    $typesetter = fakeTypesetter(mainFace());
    $lefts = fn (?float $width, TextAlign $align) => array_column(lineBoxes($typesetter->set("aa\nb", font10(), $width, $align)->lines), 3);

    expect($lefts(30.0, TextAlign::LEFT))->toEqual([0.0, 0.0])
        ->and($lefts(30.0, TextAlign::CENTER))->toEqual([10.0, 12.5])
        ->and($lefts(30.0, TextAlign::RIGHT))->toEqual([20.0, 25.0])
        ->and($lefts(null, TextAlign::RIGHT))->toEqual([0.0, 5.0]);
});

it('draws a character the font lacks with the face the catalog finds, and the line grows to that face', function (): void {
    $tall = new FakeFace(new FontSource('tall.ttf'), [0x5D0], [1000, 300, 100]);
    $text = fakeTypesetter(mainFace(), $tall)->set("a\u{5D0}\u{5D0}b", font10());
    [$line] = $text->lines;

    expect(array_map(fn (GlyphRun $run) => [$run->source->path, $run->glyphs, $run->x], $line->runs))->toBe([
        ['main.ttf', [0x61], [0.0]],
        ['tall.ttf', [0x5D0, 0x5D0], [5.0, 10.0]],
        ['main.ttf', [0x62], [15.0]],
    ])->and([$line->baseline, $line->height])->toEqual([10.0, 14.0]);
});

it('keeps a stretch in the face before it when that face has the character, rather than the catalog\'s first choice', function (): void {
    $firstChoice = new FakeFace(new FontSource('first.ttf'), [0x5D1]);
    $wider = new FakeFace(new FontSource('wider.ttf'), [0x5D0, 0x5D1]);
    [$line] = fakeTypesetter(mainFace(), $firstChoice, $wider)->set("\u{5D0}\u{5D1}", font10())->lines;

    expect(array_map(fn (GlyphRun $run) => $run->source->path, $line->runs))->toBe(['wider.ttf']);
});

it('keeps a character in its face when a joiner or variation selector the face lacks follows it', function (): void {
    $alsoHasA = new FakeFace(new FontSource('other.ttf'), [0x61, 0xFE0F]);
    [$line] = fakeTypesetter(mainFace(), $alsoHasA)->set("a\u{FE0F}b", font10())->lines;

    expect(array_map(fn (GlyphRun $run) => $run->source->path, $line->runs))->toBe(['main.ttf']);
});

it('places each glyph at the pen plus its offset, y down from the baseline', function (): void {
    $text = fakeTypesetter(mainFace([0x62 => [1.5, 2.0]]))->set('ab', font10());
    [$run] = $text->lines[0]->runs;

    expect($run->x)->toBe([0.0, 6.5])
        ->and($run->y)->toBe([8.0, 6.0]);
});

it('refuses text that is not UTF-8, and a width that is not above 0', function (): void {
    $typesetter = fakeTypesetter(mainFace());

    expect(fn () => $typesetter->set("a\xC3", font10()))->toThrow(TypographyException::class, 'UTF-8')
        ->and(fn () => $typesetter->set('a', font10(), 0.0))->toThrow(TypographyException::class, 'width');
});
