import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, PageTitle } from '@/components/ui/card';

type Item = string | { text: string; items: string[] };
type Section = { title: string; paragraphs?: string[]; items?: Item[] };

export default function Rules({ variant, sections }: { variant: 'au' | 'uk'; sections: Section[] }) {
    const back = () => (window.history.length > 1 ? window.history.back() : window.close());

    return (
        <>
            <Head title="Indemnity statement" />
            <div className="animate-rise space-y-4">
                <PageTitle eyebrow="Please read carefully" title={variant === 'au' ? 'Indemnity statement' : 'Terms & conditions'} />

                {sections.map((section) => (
                    <Card key={section.title} className="space-y-3">
                        <h2 className="font-display text-2xl font-bold uppercase text-brand">{section.title}</h2>
                        {section.paragraphs?.map((paragraph) => (
                            <p key={paragraph} className="leading-relaxed text-ink-100">
                                {paragraph}
                            </p>
                        ))}
                        {section.items && (
                            <ol className="space-y-3">
                                {section.items.map((item, index) => (
                                    <li key={index} className="flex gap-3 leading-relaxed text-ink-300">
                                        <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-md bg-ink-800 font-display text-sm font-bold text-ink-100">{index + 1}</span>
                                        {typeof item === 'string' ? (
                                            <span>{item}</span>
                                        ) : (
                                            <span>
                                                {item.text}
                                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                                    {item.items.map((sub) => (
                                                        <li key={sub}>{sub}</li>
                                                    ))}
                                                </ul>
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Card>
                ))}

                <Button variant="secondary" size="lg" block onClick={back}>
                    <ArrowLeft /> Back
                </Button>
            </div>
        </>
    );
}
