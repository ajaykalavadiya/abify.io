<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    /** Subjects the contact form accepts, mirrored by the store() validation. */
    private const CONTACT_TOPICS = ['sales', 'support', 'billing', 'other'];

    public function pricing(): Response
    {
        /** @var array<string, array{label: string, sites: int, tests_per_site: int, heatmaps_per_month: int, recordings_per_day: int}> $plans */
        $plans = config('billing.plans');

        // Read straight from the billing config so the advertised limits can
        // never drift from the ones SiteController/ExperimentController
        // actually enforce. Prices stay literal here — Stripe holds the real
        // amounts, config only carries the price IDs.
        return Inertia::render('marketing/pricing', [
            'pricing' => [
                'trialDays' => config('billing.trial_days'),
                'maxUsers' => config('billing.max_users_per_company'),
                // Prices come from config too now, rather than being written
                // twice — once here and once on the page.
                'monthly' => config('billing.prices.basic.monthly'),
                'yearly' => config('billing.prices.basic.yearly'),
                'growthMonthly' => config('billing.prices.growth.monthly'),
                'growthYearly' => config('billing.prices.growth.yearly'),
                'trial' => $plans['trial'],
                'basic' => $plans['basic'],
                'growth' => $plans['growth'],
            ],
        ]);
    }

    public function features(): Response
    {
        return Inertia::render('marketing/features');
    }

    public function featureShow(string $slug): Response
    {
        $feature = collect($this->featureDetails())->firstWhere('slug', $slug);

        abort_if($feature === null, 404);

        return Inertia::render('marketing/features/show', [
            'feature' => $feature,
        ]);
    }

    public function blogIndex(): Response
    {
        return Inertia::render('marketing/blog/index', [
            'posts' => array_map(
                fn (array $post) => collect($post)->except('body')->all(),
                $this->posts(),
            ),
        ]);
    }

    public function blogShow(string $slug): Response
    {
        $post = collect($this->posts())->firstWhere('slug', $slug);

        abort_if($post === null, 404);

        return Inertia::render('marketing/blog/show', [
            'post' => $post,
        ]);
    }

    public function why(): Response
    {
        return Inertia::render('marketing/why');
    }

    public function careers(): Response
    {
        return Inertia::render('marketing/careers/index', [
            'openings' => array_map(
                fn (array $role) => collect($role)
                    ->only(['slug', 'title', 'team', 'type', 'location', 'summary'])
                    ->all(),
                $this->openings(),
            ),
        ]);
    }

    public function careersShow(string $slug): Response
    {
        $role = collect($this->openings())->firstWhere('slug', $slug);

        abort_if($role === null, 404);

        return Inertia::render('marketing/careers/show', [
            'role' => $role,
        ]);
    }

    public function contact(Request $request): Response
    {
        // ?topic= lets entry points preselect a subject (the home page's
        // "Book a consultation call" arrives as sales). Anything unrecognised
        // falls back rather than reaching the select as an invalid value.
        $topic = (string) $request->query('topic', 'sales');

        return Inertia::render('marketing/contact', [
            'topic' => in_array($topic, self::CONTACT_TOPICS, true) ? $topic : 'sales',
        ]);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'topic' => ['required', 'string', Rule::in(self::CONTACT_TOPICS)],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $to = config('mail.from.address');

        $lines = [];

        foreach ($validated as $field => $value) {
            $lines[] = ucfirst($field).': '.($value ?? '—');
        }

        $body = implode("\n", $lines);

        Mail::raw($body, function (Message $mail) use ($to, $validated) {
            $mail->to($to)
                ->replyTo($validated['email'], $validated['name'])
                ->subject('Contact form: '.$validated['topic']);
        });

        return back()->with('status', 'contact-sent');
    }

    /**
     * Open roles. Static for now, like the blog — there's no ATS behind this,
     * applications arrive by email.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function openings(): array
    {
        return [
            [
                'slug' => 'business-development-associate',
                'title' => 'Business Development Associate',
                'team' => 'Growth',
                'type' => 'Full-time',
                'location' => 'Remote',
                'summary' => 'Find the teams who are already trying to run experiments, and make it obvious we can help.',
                'about' => "abify.io is a small, self-serve product: A/B testing, heatmaps, and analytics from one script tag, at a flat monthly price. Most of our signups arrive on their own, and this role is about reaching the ones who never hear about us — agencies running sites for several clients, in-house growth teams stuck waiting on engineering, and founders who gave up on testing because the setup cost too much.\n\nYou'd be the first dedicated commercial hire, which means the playbook isn't written yet. You'll be figuring out which segments actually convert, not executing a script someone handed you.",
                'responsibilities' => [
                    'Build and work an outbound pipeline aimed at agencies and small growth teams',
                    'Run product demos — you will know the tool well enough to set up a real test live on a call',
                    'Own the first response to inbound sales enquiries from the contact form',
                    'Keep a written record of why deals are won and lost, and bring it back to the product',
                    'Test your own outreach the way our customers test their pages, and report what worked',
                ],
                'requirements' => [
                    '1–3 years in a sales, partnerships, or growth role at a software company',
                    'Comfortable writing your own outreach rather than sending a template',
                    'Enough technical curiosity to explain CSS selectors and URL targeting without hand-waving',
                    'Clear written English — most first contact happens in writing',
                ],
                'nice' => [
                    'You have run A/B tests yourself, in any tool',
                    'Experience selling to agencies or e-commerce teams',
                ],
            ],
            [
                'slug' => 'customer-executive-assistant',
                'title' => 'Customer Executive Assistant',
                'team' => 'Customer',
                'type' => 'Full-time',
                'location' => 'Remote',
                'summary' => 'Get new accounts installed and running, and be the person who answers when something breaks.',
                'about' => "Installing a testing script and getting a first test live is where most people either succeed or quietly give up. This role owns that moment: onboarding new accounts, answering support, and making sure nobody is stuck on something we could have explained better.\n\nIt is a support role with real product access — you'll be in the dashboard daily, reproducing issues, checking whether a selector matched, and telling engineering what actually happened rather than forwarding a screenshot.",
                'responsibilities' => [
                    'Answer support and onboarding email, and get new accounts to a first running test',
                    'Reproduce reported problems against a real site before escalating them',
                    'Keep the feature pages and help content accurate as the product changes',
                    'Handle billing and account admin questions, including plan changes and invitations',
                    'Summarise recurring questions into product feedback — the same question three times is a bug in the product or the docs',
                ],
                'requirements' => [
                    '1–3 years in customer support, success, or an operations role',
                    'Comfortable in a browser devtools panel — inspecting an element, reading the console',
                    'Patient, precise writing: most support here is asynchronous',
                    'Organised enough to own a shared inbox without things falling through',
                ],
                'nice' => [
                    'Basic HTML and CSS reading ability',
                    'You have onboarded customers onto a technical product before',
                ],
            ],
        ];
    }

    /**
     * Cover art per topic rather than per post, so every article in a category
     * carries the same illustration and a new post can't end up with none.
     *
     * @var array<string, string>
     */
    private const TOPIC_IMAGES = [
        'Targeting' => '/images/features/ab-testing.png',
        'Heatmaps' => '/images/features/heatmaps.png',
        'Test types' => '/images/features/split-url-tests.png',
        'Analytics' => '/images/features/analytics.png',
        'Engineering' => '/images/hero-ab-testing.png',
    ];

    /**
     * Static sample content for now — no CMS/database model behind the blog
     * yet, just enough to have real pages to design around. Sorted newest
     * first here rather than by hand, so adding an entry anywhere is safe.
     *
     * @return array<int, array<string, string>>
     */
    protected function posts(): array
    {
        $posts = $this->postEntries();

        usort($posts, fn (array $a, array $b) => strcmp($b['date'], $a['date']));

        return array_map(
            fn (array $post) => [...$post, 'image' => self::TOPIC_IMAGES[$post['topic']]],
            $posts,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function postEntries(): array
    {
        return [
            [
                'slug' => 'conversion-rate-is-not-revenue',
                'topic' => 'Analytics',
                'title' => 'Your winning variation might be losing you money',
                'excerpt' => 'Conversion rate and revenue per visitor disagree more often than people expect — and the rate is the one that lies to you.',
                'date' => '2026-09-14',
                'body' => "Here is a result that looks unambiguous. Variation B converts at 4.1%, control at 3.4%. B wins by a fifth. Ship it.\n\nNow add the amounts. B's average order is \$38; control's is \$52. Per hundred visitors, B earns \$156 and control earns \$177. The variation that converts better sells less, and the conversion rate had no way of telling you.\n\nThis is not an edge case on a store. Anything that reduces friction tends to pull in a slightly less committed buyer — a shorter form, a louder discount, a simplified product page. More people finish, and they spend less each. Whether that is good depends entirely on which number you were optimising, and if the only number on screen is a rate, you will optimise the rate.\n\nThe metric that settles it is revenue per visitor: total revenue divided by everyone who saw the variation, spenders and non-spenders alike. It folds the rate and the basket size into one number, and it is the number your business actually experiences.\n\nTwo details worth getting right. A repeat customer counts once toward conversion rate and every time toward revenue — they are one person who converted and three orders that are all real, and conflating those is how a conversion rate ends up above 100%. And revenue per visitor is a far noisier measurement than a rate, because one unusually large order moves it in a way one extra conversion never does. Give a revenue test more time than a rate test, and be suspicious of a result that arrived quickly.",
            ],
            [
                'slug' => 'heatmaps-by-variation',
                'topic' => 'Heatmaps',
                'title' => 'Reading a heatmap of one variation, not all your traffic',
                'excerpt' => 'An unsegmented heatmap during a live test is two different pages averaged together, which is a picture of neither.',
                'date' => '2026-09-12',
                'body' => "While a test is running, a plain heatmap of that page is a blend. Half those clicks came from people who saw control, half from people who saw the variation, and the map shows you the average of two layouts that do not exist.\n\nThat average is not just imprecise, it is actively misleading. Move a button in variation B and the combined click map shows a warm patch in both the old position and the new one, as though visitors were clicking in two places. Nobody did. Two different groups each clicked once, in the only place their version of the page offered.\n\nSegmenting by variation fixes it, and it is where heatmaps stop being decorative. The test report tells you B lost. The variation heatmap tells you B's visitors scrolled straight past the call to action that B moved above the fold — which is a different problem from the one you thought you were testing, and a much more useful one to know.\n\nA few things to look for once the maps are split. Attention that stops short in one variation and not the other usually means something above it is reading as the end of the page. A hot spot on a non-clickable element is a stronger signal than a hot spot on a link, because it is visitors telling you what they expected to be clickable. And a variation whose scroll depth collapses in the first screen has usually failed before the change you made ever entered the frame.\n\nOne caveat: segmented maps are drawn from a fraction of the traffic, so they hit useful density later than the combined one. Early in a test, a sparse variation map is telling you about sample size, not about behaviour.",
            ],
            [
                'slug' => 'why-shopify-checkout-cannot-be-tested',
                'topic' => 'Targeting',
                'title' => 'Why nobody can A/B test your Shopify checkout',
                'excerpt' => 'It is not a missing feature. Shopify sandboxes the checkout, and how tools handle that tells you something about them.',
                'date' => '2026-09-10',
                'body' => "On a non-Plus Shopify store, no third-party script runs on the checkout. That is not a gap any testing tool can close — Shopify serves checkout from its own context specifically so nothing can reach in and change it.\n\nThis is worth knowing before you buy anything, because it shapes what honest tools can offer. You can test the home page, collection pages, product pages and the cart. You cannot test the checkout itself. A vendor promising Shopify checkout tests is either describing Shopify Plus, describing checkout extensions, which are a different and much more constrained thing, or describing something that will not work.\n\nThe sandbox creates a second, quieter problem: attribution. The order does not arrive in the browser where your test ran. It arrives on your server as a webhook, potentially hours later, from a connection that knows nothing about which variation that shopper saw. Something has to carry the visitor's identity across that gap.\n\nThe supported route is cart attributes. The storefront writes an identifier into the cart, Shopify carries it through checkout, and it surfaces on the order — which the webhook can then read back. It is unglamorous plumbing and it is the entire reason revenue attribution works on Shopify at all.\n\nOne consequence to expect: a shopper who reaches checkout without the storefront script having run is not attributed to any variation. That is correct, not a bug. Their order is not evidence about a test they were never in, and a tool that guesses instead is inventing data.",
            ],
            [
                'slug' => 'multivariate-testing-when-it-earns-its-keep',
                'topic' => 'Test types',
                'title' => 'Multivariate testing: when it earns its keep, and when it wastes your traffic',
                'excerpt' => 'Testing headline and button and image at once sounds efficient. Whether it is depends entirely on how much traffic you have.',
                'date' => '2026-09-09',
                'body' => "A multivariate test takes several factors — headline, button colour, hero image — and builds every combination of their options. Two options each across three factors is eight variations, and that is the whole problem in one sentence: your traffic gets divided eight ways instead of two.\n\nThe honest rule is that MVT is a high-traffic technique. If an A/B test on your page would take two weeks to reach a usable sample, the equivalent eight-arm multivariate test takes roughly four times as long, because each arm needs its own sample. On most sites that turns a two-week test into a two-month one, and two months is long enough for the season, the traffic mix, and the page itself to change underneath you.\n\nWhen it does earn its keep: high-traffic pages where you genuinely suspect the factors interact — where a bolder headline only works with the calmer image, say. That interaction is the thing an MVT can find and a sequence of A/B tests cannot.\n\nWhen it doesn't: everywhere else. If you just want to know which of three headlines wins, that is a single A/B test with three variations, and it will answer you in a quarter of the time. We cap combinations deliberately for this reason — a factorial explosion is easy to configure and nearly impossible to conclude.",
            ],
            [
                'slug' => 'picking-a-goal-that-means-something',
                'topic' => 'Analytics',
                'title' => 'Picking a goal that actually means something',
                'excerpt' => 'Optimising for clicks on a button is easy. Optimising for the thing that pays you is harder, and usually a different number.',
                'date' => '2026-08-25',
                'body' => "The goal you pick decides what your test is allowed to tell you, and the most common mistake is picking one that sits too early in the journey.\n\nButton clicks are the classic example. Make a call-to-action louder and more people will click it — that is nearly guaranteed. Whether more people finish checkout is a completely separate question, and a louder button that pulls in unconvinced visitors can raise clicks while lowering completed orders. If your goal is the click, the test reports a win and you ship a regression.\n\nThe fix is to measure as close to the money as you can stand. A custom event on order completion or signup finished is a better goal than a pageview on a thank-you page, which is in turn better than a click. The tradeoff is volume: later goals convert at lower rates, so they need more traffic to reach a conclusion. That tradeoff is real, and taking the earlier goal because it is faster is a decision to accept a weaker answer — which is fine, as long as it is a decision rather than an accident.\n\nA useful habit is recording both: set your real goal as the one you judge the test on, and keep an eye on the intermediate number for diagnosis. If clicks rose and completions did not, you have learned something specific about who the new copy attracts.",
            ],
            [
                'slug' => 'why-url-targeting-matters',
                'topic' => 'Targeting',
                'title' => 'Why URL targeting matters more than your test design',
                'excerpt' => 'A test that runs on the wrong page is worse than no test at all — here\'s how exact, contains, and regex matching actually work.',
                'date' => '2026-08-12',
                'body' => "Most failed A/B tests aren't failures of statistics — they're failures of targeting. A headline test that fires on every page of your site, including ones where that headline doesn't even exist, quietly pollutes your results with noise.\n\nExact match is the safest default: the test only runs on the literal URL you specify. Contains match is useful for parameterized pages (`/product/` matches every product page regardless of ID). Regex match is the escape hatch for everything else — but it's also the easiest way to accidentally match far more traffic than you intended, so test your pattern against real URLs before shipping.\n\nThe rule of thumb: start narrow. It's much easier to broaden a working test's targeting than to figure out why a broad test's numbers don't make sense.",
            ],
            [
                'slug' => 'reading-a-heatmap-without-lying-to-yourself',
                'topic' => 'Heatmaps',
                'title' => 'Reading a heatmap without lying to yourself',
                'excerpt' => 'A red cluster isn\'t automatically a win or a problem. Here\'s what to actually check before you change anything.',
                'date' => '2026-07-30',
                'body' => "It's tempting to look at a click heatmap, see a hot spot on your logo, and conclude people are confused. Sometimes they are. Often they're just... clicking your logo to go home, which is completely normal behavior that says nothing about your page.\n\nBefore acting on a heatmap: check sample size (a few dozen sessions can produce a misleading pattern), check device mix (mobile and desktop scroll and click very differently — aggregate them separately if you can), and check whether the 'problem' area is actually interactive. A hot spot on a non-clickable element is a much stronger signal than one on a link.\n\nHeatmaps are best used to generate hypotheses, not conclusions. Pair them with an actual test before you commit to a redesign.",
            ],
            [
                'slug' => 'split-url-vs-element-changes',
                'topic' => 'Test types',
                'title' => 'Split URL tests vs. element changes: when to use which',
                'excerpt' => 'Redirecting to a whole new page and swapping an element on the same page solve different problems — mixing them up wastes both.',
                'date' => '2026-07-08',
                'body' => "Element-change tests (swap text, hide something, restyle a button) are for incremental questions on a page you're mostly happy with: does a green button convert better than blue? Does a shorter headline help?\n\nSplit URL tests are for bigger, structural questions — comparing a rebuilt page against the original, testing a completely different layout, or trying a different flow (single long page vs. a multi-step form). They cost more to build because you need two real pages, but they let you test changes that are too disruptive to express as an element swap.\n\nA common mistake: using an element-change test to simulate a much bigger redesign by injecting a wall of custom CSS/JS. If your 'variation' is effectively a second page rendered through hacks, it should probably just be a split URL test — you'll get cleaner results and a much easier time debugging when something looks wrong.",
            ],
            [
                'slug' => 'why-your-test-flickers',
                'topic' => 'Engineering',
                'title' => 'Why your test flickers, and what to do about it',
                'excerpt' => 'That half-second where the original shows before the variation is not cosmetic — it changes the result you measure.',
                'date' => '2026-06-30',
                'body' => "Flicker is when a visitor sees the original page for a moment before the variation replaces it. It looks like a rendering glitch. It is actually a measurement problem: part of your variation group saw the control, so the two groups are no longer cleanly separated, and the difference you measure is diluted toward zero.\n\nThe cause is almost always ordering. The snippet must run and apply its changes before the browser paints, so anything that delays it — loading it at the bottom of the page, loading it asynchronously, putting a slow script in front of it — opens the window where the original is visible. The snippet hides the page while it works precisely to close that window.\n\nThree things fix nearly every case. Put the snippet in the head, as early as possible, and load it synchronously — this is the one script where async is actively harmful. Keep the hidden-element rule scoped to what you actually change if you can, rather than the whole body, so the reveal is less jarring. And keep an eye on the timeout: it exists so a slow network shows the original rather than a blank page, but if you are hitting it regularly, the fix is the load order, not a longer timeout.\n\nIf flicker persists on one specific page, check what else runs before us there. A synchronous third-party tag ahead of the snippet delays everything behind it.",
            ],
            [
                'slug' => 'when-is-a-result-real',
                'topic' => 'Analytics',
                'title' => 'When is a result real? Reading conversion numbers honestly',
                'excerpt' => 'A variation that is 20% ahead on day two is usually noise. Here is what to look at before you call a winner.',
                'date' => '2026-06-19',
                'body' => "The most expensive mistake in testing is stopping early. Conversion rates swing wildly at low sample sizes — a variation sitting 20% ahead after two days and forty conversions is, more often than not, telling you nothing at all.\n\nThree things to check before calling a winner. First, absolute conversion counts, not just the percentage: a 6% versus 4% gap means something very different at 50 visitors than at 5,000. Second, whether the gap has been stable or has been crossing back and forth — a lead that flips every day is noise wearing a costume. Third, whether the test ran over whole weeks: weekday and weekend traffic often convert differently, and a test that ran Tuesday to Friday has quietly sampled only part of your audience.\n\nThe uncomfortable truth is that most tests on most sites never reach a conclusive result, and that is useful information too. A change that cannot be distinguished from control after real traffic is a change that does not matter much — ship whichever version you prefer for other reasons, and go test something bigger.",
            ],
            [
                'slug' => 'one-script-tag-and-what-it-does',
                'topic' => 'Engineering',
                'title' => 'What actually happens when that one script tag loads',
                'excerpt' => 'Hiding the page, bucketing the visitor, applying changes, revealing — with a timeout so a bad network never leaves you blank.',
                'date' => '2026-05-28',
                'body' => "A testing snippet has an awkward job: it has to change the page before anyone sees the original, without ever risking a blank page if something goes wrong. Here is the sequence.\n\nFirst, the snippet hides the body with an injected style rule and starts a timer. Then it requests the settings for your site — which tests are running, what they target, what they change. If the visitor matches a test's URL rule, they are bucketed deterministically from an ID stored in a cookie, so the same person sees the same variation on every visit for the life of the test. The changes for that variation are applied to the matching elements, and only then is the page revealed.\n\nThe timer is the important part. If the request is slow, fails, or the script never loads at all, the reveal fires anyway after the timeout and the visitor sees your original page. A testing tool that can white-screen your site under a bad network is worse than no testing tool, so the failure mode is always: show the original, lose the test, keep the visitor.",
            ],
            [
                'slug' => 'what-scroll-depth-actually-tells-you',
                'topic' => 'Heatmaps',
                'title' => 'What scroll depth actually tells you (and what it does not)',
                'excerpt' => 'Half your visitors never reach the middle of the page. That is normal — the useful signal is where the drop is sharp.',
                'date' => '2026-05-06',
                'body' => "Every scroll-depth chart looks alarming the first time. Attention falls away steadily down the page, and by the halfway mark a large share of visitors are gone. That gradual decay is not a problem to fix; it is what reading on the web looks like.\n\nThe signal worth acting on is not the slope, it is the cliff. A steady decline means people are drifting off as they lose interest. A sudden drop between two points means something at that spot stopped them — a section that reads like the end of the page, a huge image that looks like a footer, a wall of text with no heading to grab. Find the cliff, look at what sits immediately above it, and you have a hypothesis worth testing.\n\nTwo cautions. Compare mobile and desktop separately, because a single screen of desktop content can be four screens of scrolling on a phone, and mixing them produces a chart that describes neither. And remember that reaching a point is not reading it — scroll depth tells you what was on screen, not what was absorbed. Pair a sharp drop with a click map before you conclude anything.",
            ],
            [
                'slug' => 'sticky-bucketing-and-why-it-matters',
                'topic' => 'Engineering',
                'title' => 'Sticky bucketing: why the same visitor must keep the same variation',
                'excerpt' => 'If someone sees version A on Monday and version B on Thursday, you are no longer running an experiment.',
                'date' => '2026-04-14',
                'body' => "Assignment has to be stable. A visitor who sees the new headline on their first visit needs to see it on every visit until the test ends, and the reason is not consistency for its own sake — it is that unstable assignment destroys the comparison you are trying to make.\n\nThink about what happens if bucketing is random on every page load. Someone who visits five times before converting has been exposed to both versions, so their conversion cannot be attributed to either. Do that across your whole audience and the two groups blur into each other; the measured difference between them shrinks toward nothing regardless of how good the variation actually is. You would need enormous traffic to detect an effect that your own tooling is busy erasing.\n\nStickiness comes from a first-party cookie holding a random visitor ID. Bucketing is derived from that ID, so the assignment is a pure function of who the visitor is and which test is running — the same input always produces the same variation, without storing a decision per person per test anywhere.\n\nThe honest limitation: if someone clears cookies, switches to their phone, or opens a private window, they are a new visitor as far as any client-side tool is concerned, and they may land in the other group. That is a real source of noise in every client-side testing product, ours included. It is small at normal traffic levels, and it is the price of not requiring a login to run a test.",
            ],
            [
                'slug' => 'five-tests-worth-running-first',
                'topic' => 'Targeting',
                'title' => 'Five tests worth running before you get clever',
                'excerpt' => 'If you have never tested anything on your site, these five answer more than the elaborate ideas will.',
                'date' => '2026-03-12',
                'body' => "The first test people want to run is usually the most interesting one. The first test worth running is usually the most boring one, because a boring test on a high-traffic page finishes, and an elaborate test on a page nobody visits does not.\n\nStart with the headline on your highest-traffic landing page. It is the thing most visitors read, it is a one-line change, and the result generalises to your other copy.\n\nSecond, the primary call to action — its wording, not its colour. 'Start free trial' and 'See how it works' promise different things and attract different people, which is a far bigger lever than a shade of blue.\n\nThird, the length of your signup form. Removing one optional field is a genuine test with a real business consequence, and it is quick to build.\n\nFourth, whether your pricing page leads with the price or the value. This one often surprises people, and it is a straightforward two-variation test.\n\nFifth, social proof placement: above the fold versus below it. It costs nothing to move an element and it tells you something about how much reassurance your visitors need before they commit.\n\nNone of these are creative. All five run on pages with enough traffic to conclude, change one thing so the result is interpretable, and teach you something that applies to the next test. Save the multi-factor redesign for after you know how your audience responds to a sentence.",
            ],
        ];
    }

    /**
     * Static sample content for now — mirrors the icon/summary cards on the
     * features pages with a longer write-up per feature. 'image' is optional:
     * only the features an illustration exists for carry one.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function featureDetails(): array
    {
        return [
            [
                'slug' => 'ab-testing',
                'image' => '/images/features/ab-testing.png',
                'title' => 'A/B testing',
                'tagline' => 'Swap text, HTML, or CSS on any element — no deploy required.',
                'body' => "An A/B test targets one or more elements on a page by CSS selector and swaps their text, HTML, or applies custom CSS — all from the dashboard, with nothing to deploy. A control is created automatically, so you always have a real baseline to compare against.\n\nEach variation gets its own traffic weight, and visitors are bucketed once per test and stay on the version they were assigned for the life of the test — no flicker, no reassignment on refresh.\n\nBecause changes are scoped to a selector rather than a whole page, most tests take minutes to set up: pick the element, describe the change, set traffic weights, and start it.",
                'highlights' => [
                    'Text, HTML, and CSS changes on any CSS-selectable element',
                    'Automatic control variation for a true baseline',
                    'Independent traffic weights per variation',
                    'Sticky per-visitor bucketing for the life of the test',
                ],
            ],
            [
                'slug' => 'url-targeting',
                'title' => 'URL targeting',
                'tagline' => 'Every test is scoped to the page(s) it actually applies to.',
                'body' => "A test that runs on the wrong page is worse than no test at all — it quietly pollutes your results with visitors who never even saw the change. URL targeting keeps that from happening by scoping every test to exact, contains, or regex matching against the page URL.\n\nExact match is the safest default for a single page. Contains match is built for parameterized routes, like matching every product page under `/product/` regardless of ID. Regex is the escape hatch for everything else, with the tradeoff that it's also the easiest way to accidentally match more traffic than intended.\n\nVisitors are only bucketed into a test on pages that match its targeting, so impressions and conversion numbers stay clean.",
                'highlights' => [
                    'Exact, contains, and regex URL matching',
                    'Visitors are only bucketed on matching pages',
                    'Keeps impressions and conversion data free of noise',
                ],
            ],
            [
                'slug' => 'split-url-tests',
                'image' => '/images/features/split-url-tests.png',
                'title' => 'Split URL tests',
                'tagline' => 'Send a percentage of visitors to a completely different page.',
                'body' => "Some changes are too big to express as an element swap — a full page rebuild, a different layout, or a different flow entirely (a single long page versus a multi-step form). Split URL tests handle that by redirecting a percentage of visitors to a separate, real page you control.\n\nAssignment is sticky per visitor and redirect-based, so nobody bounces between versions mid-session, and conversion is measured and compared against the original exactly the same way as an element-change test.\n\nThey cost a bit more to set up than an element change, since you need two real pages, but they let you test changes that would otherwise require a wall of custom CSS/JS hacked onto a single page.",
                'highlights' => [
                    'Redirect-based, sticky per visitor',
                    'Compare a full rebuild against the original',
                    'Same conversion tracking and reporting as element tests',
                ],
            ],
            [
                'slug' => 'heatmaps',
                'image' => '/images/features/heatmaps.png',
                'title' => 'Heatmaps',
                'tagline' => 'See where attention actually goes, from the same script.',
                'body' => "Clicks, mouse movement, and scroll depth are captured automatically from the same one-line tracking script used for testing and analytics — no separate integration, no second snippet to install.\n\nData is aggregated per page, so you can see exactly where visitors click, how far they scroll before giving up, and where their mouse lingers. It's most useful for generating hypotheses: a hot spot on a non-clickable element is a much stronger signal than one on a link, and a low scroll-depth number on a long page tells you something concrete about where you're losing people.\n\nPair a heatmap with an actual test before committing to a redesign based on it alone.",
                'highlights' => [
                    'Click, mouse movement, and scroll-depth tracking',
                    'Aggregated automatically per page',
                    'No separate script or integration required',
                ],
            ],
            [
                'slug' => 'variation-heatmaps',
                'image' => '/images/features/heatmaps.png',
                'title' => 'Heatmaps by variation',
                'tagline' => 'See the behaviour behind the number, one variation at a time.',
                'body' => "A conversion rate tells you a variation lost. It does not tell you why. Heatmaps split by variation answer the second question: the same click, attention and scroll maps, drawn only from the visitors who actually saw that version of the page.\n\nSet it from the heatmap's own filter, or jump straight there from a variation's row in the test report. Every layer is segmented together — the points, the scroll bands and the session counts all come from the same people, because a map that mixed one variation's clicks with another's scroll depth would be worse than no map at all.\n\nThe most useful comparisons are usually the unglamorous ones. A variation that moved the call to action above the fold and still lost will often show visitors scrolling straight past it, which is a different problem from the one you thought you were testing.\n\nVisitors who were never bucketed into the test are excluded from every variation rather than being folded into one, so what you are looking at is only ever the people the result was calculated from.",
                'highlights' => [
                    'Click, attention and scroll maps per variation',
                    'Jump straight from a variation row in the test report',
                    'Points, scroll depth and session counts segmented together',
                    'Unenrolled visitors excluded from every variation',
                ],
            ],
            [
                'slug' => 'revenue-tracking',
                'title' => 'Revenue tracking',
                'tagline' => 'Test against money, not just clicks.',
                'body' => "A conversion rate answers whether more people did something. It does not answer whether you made more money, and on a store those are routinely different questions. A variation that converts less often but sells more per order is the one you want to ship, and a rate alone will tell you to throw it away.\n\nOrders are attributed to the variation the buyer actually saw and reported as revenue per visitor and average order value alongside the conversion rate. Revenue is stored in the currency it was transacted in and never converted, so a rate that moved between the order and the report cannot quietly rewrite a finished experiment's result.\n\nOne detail worth knowing: a repeat customer counts once toward the conversion rate and every time toward revenue. They are one person who converted, and three orders that are all real.",
                'highlights' => [
                    'Revenue per visitor and average order value per variation',
                    'Orders attributed to the variation the buyer saw',
                    'Stored in the original currency, never converted',
                    'Repeat buyers counted once for rate, fully for revenue',
                ],
            ],
            [
                'slug' => 'server-side-conversions',
                'title' => 'Server-side conversions',
                'tagline' => 'Record revenue from places a browser cannot reach.',
                'body' => "Some of the most important things that happen in a funnel happen where no script of ours runs — a hosted checkout, a payment that settles later, an order that is refunded the next morning. A browser beacon cannot see any of it.\n\nThe conversions API takes those events directly from your server with an authenticated request: the visitor, the goal, the amount, and your own order id. It is the same path the Shopify, WooCommerce and Magento integrations use, so whatever you build on it behaves identically to the ones we ship.\n\nThat order id is what makes it safe to retry. Every platform redelivers webhooks, and a duplicate delivery is silently wrong in a way an error never is — a double-counted order does not look like a bug, it looks like a better result. Conversions are deduplicated on your id, so a webhook can fire as many times as it likes.",
                'highlights' => [
                    'Authenticated per-site write key, separate from the public snippet',
                    'Deduplicated on your own order id, so retries are safe',
                    'Revenue, currency and custom properties per conversion',
                    'The same API the Shopify integration uses',
                ],
            ],
            [
                'slug' => 'session-recording',
                'title' => 'Session recording',
                'tagline' => 'Watch the visit, filtered by the variation they saw.',
                'body' => "Recordings replay a real visit — what moved, what was clicked, what was hesitated over. Filtered by variation, they turn \"B converted worse\" into something you can actually watch happen.\n\nCapture is capped per day and sampled, per site, because recording every visit is neither affordable nor useful. You set both numbers, so cost stays predictable rather than scaling with your traffic, and a recording that starts is always finished rather than cut off halfway when a quota rolls over.\n\nInputs are masked by default, not as an option you have to find. Recordings routinely cross checkout and account pages, and the cost of leaking a customer's address is not comparable to the cost of a slightly less readable replay. Recordings are deleted automatically once they pass your retention window.\n\nBecause there will always be more recordings than anyone will watch, rage clicks and JavaScript errors are detected during capture and filterable — so the list starts with the visits worth opening.",
                'highlights' => [
                    'Filter recordings by the variation the visitor saw',
                    'Daily cap and sample rate you control, per site',
                    'Inputs masked by default; automatic retention cleanup',
                    'Rage clicks and JS errors flagged for triage',
                ],
            ],
            [
                'slug' => 'shopify',
                'title' => 'Shopify',
                'tagline' => 'Attribute real orders to the variation that earned them.',
                'body' => "Install from the theme editor — an app embed block, no theme code to edit and nothing to paste. Test your home, collection, product and cart pages, and read the results in revenue rather than clicks.\n\nShopify's checkout is sandboxed, so no third-party script runs at the moment of purchase and the order arrives as a webhook hours later, on a connection that knows nothing about the browser that saw the test. The integration bridges that by writing the visitor into a cart attribute, which Shopify carries through checkout and onto the order.\n\nPaid orders are recorded, not placed ones: an order that is never paid is not revenue, and counting it would overstate any variation that happens to attract abandoned checkouts.\n\nWorth being straight about the limit — you cannot test the checkout itself on non-Plus stores. Shopify does not allow it, and any tool that claims otherwise is describing something else.",
                'highlights' => [
                    'One-click install as a theme app embed, no theme edits',
                    'Paid orders attributed to the variation the buyer saw',
                    'Deduplicated automatically when Shopify retries a webhook',
                    'Storefront pages only — Shopify sandboxes its checkout',
                ],
            ],
            [
                'slug' => 'analytics',
                'image' => '/images/features/analytics.png',
                'title' => 'Analytics',
                'tagline' => 'Impressions and conversion rate per variation, compared against control.',
                'body' => "Every test reports impressions and conversion rate per variation, compared directly against control, so you can tell whether a result is real or just noise before you ship it permanently.\n\nGoals can be a simple pageview or a custom event, tracked from the same script that runs the test itself — there's no separate analytics tool to wire up or reconcile against.\n\nBecause the same visitor bucket feeds testing, heatmaps, and analytics, the numbers you see for a variation and the heatmap for that same variation are always talking about the same traffic.",
                'highlights' => [
                    'Impressions and conversion rate per variation',
                    'Pageview and custom-event goals',
                    'One visitor bucket shared across testing, heatmaps, and analytics',
                ],
            ],
            [
                'slug' => 'live-preview-debugging',
                'title' => 'Live preview & debugging',
                'tagline' => 'Force any variation live and see exactly what matched.',
                'body' => "Waiting on random bucketing to see a variation live is slow and unreliable for QA. A preview link forces any specific variation on your live site instantly, without it counting as real traffic or affecting your results.\n\nAlongside the forced variation, a debug panel shows exactly what matched: which test fired, which URL rule matched it, which elements were found and changed, and — just as importantly — which selectors didn't match anything, so a silently broken test doesn't run for days before anyone notices.",
                'highlights' => [
                    'One-link forced preview per variation',
                    'Excluded from real traffic and results',
                    'Debug panel showing exactly what matched and applied',
                ],
            ],
        ];
    }
}
