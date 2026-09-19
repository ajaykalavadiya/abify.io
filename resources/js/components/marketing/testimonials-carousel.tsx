import { ChevronLeft, ChevronRight, Quote } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Testimonial = {
    initials: string;
    name: string;
    role: string;
    quote: string;
};

type Chip = { bg: string; text: string };

export function TestimonialsCarousel({ items, chips }: { items: Testimonial[]; chips: Chip[] }) {
    const trackRef = useRef<HTMLDivElement>(null);

    function scroll(direction: 1 | -1) {
        const el = trackRef.current;
        if (!el) return;
        el.scrollBy({ left: direction * el.clientWidth * 0.85, behavior: 'smooth' });
    }

    return (
        <div className="relative">
            <div
                ref={trackRef}
                className="scrollbar-hide flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2"
            >
                {items.map((item, index) => {
                    const chip = chips[index % chips.length];
                    return (
                        <Card
                            key={item.name}
                            className="w-[85%] shrink-0 snap-start rounded-2xl sm:w-[46%] lg:w-[31%]"
                        >
                            <CardContent className="flex h-full flex-col gap-4">
                                <Quote className={`size-6 ${chip.text} opacity-70`} />
                                <p className="flex-1 text-sm text-balance">"{item.quote}"</p>
                                <div className="flex items-center gap-3 pt-2">
                                    <div
                                        className={`flex size-10 shrink-0 items-center justify-center rounded-none text-sm font-semibold ${chip.bg} ${chip.text}`}
                                    >
                                        {item.initials}
                                    </div>
                                    <div>
                                        <div className="text-sm font-semibold">{item.name}</div>
                                        <div className="text-muted-foreground text-xs">{item.role}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <div className="mt-4 flex justify-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    onClick={() => scroll(-1)}
                    aria-label="Previous testimonials"
                >
                    <ChevronLeft />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    onClick={() => scroll(1)}
                    aria-label="Next testimonials"
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
