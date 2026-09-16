import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2.5 34 8v9.6c0 9.1-5.9 16.8-14 19.9-8.1-3.1-14-10.8-14-19.9V8l14-5.5Zm0 5.2L10.8 11v6.6c0 6.4 3.8 12.1 9.2 14.8 5.4-2.7 9.2-8.4 9.2-14.8V11L20 7.7Zm-1.8 5.4h3.6v5.1h5.1v3.6h-5.1v5.1h-3.6v-5.1h-5.1v-3.6h5.1v-5.1Z" />
        </svg>
    );
}
