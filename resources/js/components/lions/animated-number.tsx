import { useEffect, useRef, useState } from 'react';
import { animate, useInView } from 'framer-motion';

export default function AnimatedNumber({
    value,
    format,
    duration = 1.4,
    className,
}: {
    value: number;
    format?: (n: number) => string;
    duration?: number;
    className?: string;
}) {
    const ref = useRef<HTMLSpanElement>(null);
    const inView = useInView(ref, { once: true, amount: 0.4 });
    const [display, setDisplay] = useState(0);
    const previousRef = useRef(0);

    useEffect(() => {
        if (!inView) {
            return;
        }

        const from = previousRef.current;
        const controls = animate(from, value, {
            duration: duration * (value === from ? 0 : 1),
            ease: 'easeOut',
            onUpdate: (latest) => setDisplay(latest),
            onComplete: () => {
                previousRef.current = value;
            },
        });

        return () => controls.stop();
    }, [value, inView, duration]);

    useEffect(() => {
        if (!inView) {
            return;
        }
        previousRef.current = display;
    }, [display, inView]);

    const formatter =
        format ?? ((n: number) => Math.round(n).toLocaleString('es-AR'));

    return (
        <span ref={ref} className={className}>
            {formatter(display)}
        </span>
    );
}
