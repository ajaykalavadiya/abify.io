import * as React from 'react';

import { cn } from '@/lib/utils';

// Lightweight tabs (no @radix-ui/react-tabs dependency) — same basic API
// shape as shadcn's tabs (Tabs/TabsList/TabsTrigger/TabsContent), backed by
// plain React state instead of a Radix primitive.

type TabsContextValue = {
    value: string;
    setValue: (value: string) => void;
};

const TabsContext = React.createContext<TabsContextValue | null>(null);

function useTabsContext() {
    const context = React.useContext(TabsContext);
    if (!context) throw new Error('Tabs.* components must be used within <Tabs>');
    return context;
}

function Tabs({
    defaultValue,
    value: controlledValue,
    onValueChange,
    className,
    children,
    ...props
}: {
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
} & Omit<React.ComponentProps<'div'>, 'onChange'>) {
    const [uncontrolledValue, setUncontrolledValue] = React.useState(defaultValue ?? '');
    const value = controlledValue ?? uncontrolledValue;

    const setValue = (next: string) => {
        setUncontrolledValue(next);
        onValueChange?.(next);
    };

    return (
        <TabsContext.Provider value={{ value, setValue }}>
            <div data-slot="tabs" className={cn('flex flex-col gap-2', className)} {...props}>
                {children}
            </div>
        </TabsContext.Provider>
    );
}

function TabsList({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="tabs-list"
            role="tablist"
            className={cn(
                'bg-muted text-muted-foreground inline-flex h-9 w-fit items-center justify-center rounded-lg p-1',
                className,
            )}
            {...props}
        />
    );
}

function TabsTrigger({ className, value, ...props }: React.ComponentProps<'button'> & { value: string }) {
    const { value: active, setValue } = useTabsContext();
    const isActive = active === value;

    return (
        <button
            type="button"
            data-slot="tabs-trigger"
            role="tab"
            aria-selected={isActive}
            data-state={isActive ? 'active' : 'inactive'}
            onClick={() => setValue(value)}
            className={cn(
                "data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:border-border text-foreground dark:text-muted-foreground inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-colors disabled:pointer-events-none disabled:opacity-50",
                className,
            )}
            {...props}
        />
    );
}

function TabsContent({ className, value, ...props }: React.ComponentProps<'div'> & { value: string }) {
    const { value: active } = useTabsContext();
    if (active !== value) return null;

    return (
        <div data-slot="tabs-content" role="tabpanel" className={cn('flex-1 outline-none', className)} {...props} />
    );
}

export { Tabs, TabsList, TabsTrigger, TabsContent };
