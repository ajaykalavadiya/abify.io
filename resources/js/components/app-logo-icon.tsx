import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/icon.png" alt="abify.io" {...props} />;
}
