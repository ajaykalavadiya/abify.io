import { Form, Head, usePage } from '@inertiajs/react';
import { BookOpen, LifeBuoy, Mail, MessageSquare, Send } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { MarketingFooter } from '@/components/marketing-footer';
import { MarketingHeader } from '@/components/marketing-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as contactStore } from '@/routes/contact';

const channels = [
    {
        icon: MessageSquare,
        title: 'Sales & demos',
        body: 'Questions about plans, limits, or whether abify fits your stack — we answer these ourselves, usually within a day.',
        chip: 'bg-primary/10 text-primary',
    },
    {
        icon: LifeBuoy,
        title: 'Support',
        body: 'Something not tracking, a test not firing, a selector not matching. Include your site URL and test name so we can look at the real thing.',
        chip: 'bg-violet-500/10 text-violet-500',
    },
    {
        icon: BookOpen,
        title: 'Docs first',
        body: 'Installation, targeting rules, and how bucketing works are written up on the feature pages — often faster than waiting on a reply.',
        chip: 'bg-rose-500/10 text-rose-500',
    },
];

const topics = [
    { value: 'sales', label: 'Sales & plans' },
    { value: 'support', label: 'Technical support' },
    { value: 'billing', label: 'Billing' },
    { value: 'other', label: 'Something else' },
];

export default function Contact({ topic }: { topic: string }) {
    // The controller flashes on success rather than returning a prop, so the
    // toast fires off the shared flash bag once per successful submit.
    const { status } = usePage().props;

    useEffect(() => {
        if (status === 'contact-sent') {
            toast.success("Thanks — your message is on its way. We'll reply by email.");
        }
    }, [status]);

    const consultation = topic === 'sales';

    return (
        <>
            <Head title="Contact" />

            <div className="bg-background text-foreground min-h-screen">
                <MarketingHeader />

                <section className="border-border border-b">
                    <div className="mx-auto max-w-6xl px-6 py-16 text-center">
                        <h1 className="text-4xl font-bold tracking-tight">Talk to us</h1>
                        <p className="text-muted-foreground mx-auto mt-4 max-w-xl text-lg text-balance">
                            Questions about plans, a test that is not behaving, or feedback on
                            something we got wrong — it all reaches the same small team.
                        </p>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 py-16">
                    <div className="grid gap-10 lg:grid-cols-[1.1fr_1fr]">
                        <div className="border-border border p-8">
                            <h2 className="text-xl font-semibold">
                                {consultation ? 'Book a consultation call' : 'Send a message'}
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {consultation
                                    ? 'Tell us what you are trying to test and we will come back with a time.'
                                    : 'Every field except company is required.'}
                            </p>

                            <Form
                                action={contactStore.url()}
                                method="post"
                                resetOnSuccess
                                className="mt-6 space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="name">Name</Label>
                                                <Input id="name" name="name" required />
                                                <InputError message={errors.name} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="email">Work email</Label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    required
                                                    placeholder="you@company.com"
                                                />
                                                <InputError message={errors.email} />
                                            </div>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="company">
                                                    Company{' '}
                                                    <span className="text-muted-foreground font-normal">
                                                        (optional)
                                                    </span>
                                                </Label>
                                                <Input id="company" name="company" />
                                                <InputError message={errors.company} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="topic">What is this about?</Label>
                                                <select
                                                    id="topic"
                                                    name="topic"
                                                    defaultValue={topic}
                                                    className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full border px-3 py-1 text-sm outline-none focus-visible:ring-[3px]"
                                                >
                                                    {topics.map((option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={errors.topic} />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="message">Message</Label>
                                            <textarea
                                                id="message"
                                                name="message"
                                                required
                                                rows={6}
                                                minLength={20}
                                                placeholder={
                                                    consultation
                                                        ? 'What does your site do, and what would you want to test first?'
                                                        : 'What are you trying to do, and what is happening instead?'
                                                }
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 w-full border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                            />
                                            <InputError message={errors.message} />
                                        </div>

                                        <Button type="submit" size="lg" disabled={processing}>
                                            {processing
                                                ? 'Sending…'
                                                : consultation
                                                  ? 'Request a call'
                                                  : 'Send message'}
                                            <Send />
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>

                        <div className="space-y-4">
                            {channels.map((channel) => (
                                <div
                                    key={channel.title}
                                    className="border-border flex items-start gap-4 border p-6"
                                >
                                    <span
                                        className={`flex size-10 shrink-0 items-center justify-center ${channel.chip}`}
                                    >
                                        <channel.icon className="size-5 text-inherit" />
                                    </span>
                                    <div>
                                        <div className="font-semibold">{channel.title}</div>
                                        <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                            {channel.body}
                                        </p>
                                    </div>
                                </div>
                            ))}

                            <div className="border-border bg-muted/40 flex items-start gap-4 border p-6">
                                <span className="text-muted-foreground flex size-10 shrink-0 items-center justify-center">
                                    <Mail className="size-5" />
                                </span>
                                <div>
                                    <div className="font-semibold">Prefer plain email?</div>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        Write to{' '}
                                        <a
                                            href="mailto:hello@abify.io"
                                            className="text-primary font-medium hover:underline"
                                        >
                                            hello@abify.io
                                        </a>{' '}
                                        and it lands in the same inbox as this form.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <MarketingFooter />
            </div>
        </>
    );
}
