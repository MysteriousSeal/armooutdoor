<?php

namespace Tests\Unit;

use App\Support\CssMinifier;
use PHPUnit\Framework\TestCase;

class CssMinifierTest extends TestCase
{
    public function test_comments_and_whitespace_go_and_the_rules_survive(): void
    {
        $css = "/* a note */\n.a ,  .b {\n    color: red;\n    margin: 0 ;\n}\n";

        $this->assertSame('.a,.b{color:red;margin:0}', CssMinifier::minify($css));
    }

    public function test_calc_keeps_the_spaces_its_operators_need(): void
    {
        $css = '.a { left: max(0.35rem, calc(50% - min(45vw, 30rem) - 3.6rem)); }';

        $this->assertSame(
            '.a{left:max(0.35rem,calc(50% - min(45vw,30rem) - 3.6rem))}',
            CssMinifier::minify($css),
        );
    }

    public function test_quoted_strings_pass_through_untouched(): void
    {
        $css = ".a::before { content: 'Voir  la  catégorie ;' ; }";

        $this->assertSame(".a::before{content:'Voir  la  catégorie ;'}", CssMinifier::minify($css));
    }

    public function test_an_apostrophe_inside_a_comment_misleads_nothing(): void
    {
        // The sheets carry French comments — « l'olive » must not read as
        // an opening quote that swallows the rules after it.
        $css = "/* l'olive des titres */ .a { color: red; } /* l'accent */ .b { color: blue; }";

        $this->assertSame('.a{color:red}.b{color:blue}', CssMinifier::minify($css));
    }

    public function test_media_queries_and_pseudo_selectors_keep_their_meaning(): void
    {
        $css = "@media (max-width: 900px) {\n  .a > .b :hover { color: red; }\n}";

        $this->assertSame('@media (max-width:900px){.a > .b :hover{color:red}}', CssMinifier::minify($css));
    }

    public function test_minifying_twice_changes_nothing(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/css/app.css');
        $once = CssMinifier::minify($css);

        $this->assertSame($once, CssMinifier::minify($once));
        $this->assertLessThan(strlen($css), strlen($once));
    }
}
