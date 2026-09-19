import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-none text-sm font-medium transition-[filter,opacity,background-color,border-color,color] duration-150 active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 disabled:active:scale-100 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "from-primary border-2 border-transparent bg-gradient-to-r to-violet-600 text-primary-foreground hover:brightness-110",
        destructive:
          "border-2 border-transparent bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "text-foreground border-2 border-transparent [background:linear-gradient(var(--background),var(--background))_padding-box,linear-gradient(90deg,#2F6FED,#7C3AED)_border-box] hover:brightness-105",
        // The rose/pink accent already used for chips, as a solid CTA. Both
        // stops are 600-weight so white label text clears 4.5:1 across the
        // whole gradient — rose-500 would only reach ~3.6:1.
        pink:
          "border-2 border-transparent bg-gradient-to-r from-rose-600 to-pink-600 text-white hover:brightness-110",
        secondary:
          "border-2 border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "border-2 border-transparent hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 px-3 has-[>svg]:px-2.5",
        lg: "h-11 px-7 text-base has-[>svg]:px-5",
        icon: "size-9",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
