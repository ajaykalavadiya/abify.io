import type { Auth } from "@/types/auth";
import type { Company, SiteOption } from "@/types/vwo";

declare module "react" {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            /** False when Google OAuth credentials are not configured. */
            googleAuthEnabled: boolean;
            auth: Auth;
            company: Company | null;
            companyRole: "owner" | "member" | null;
            sites: SiteOption[] | null;
            currentSite: SiteOption | null;
            status: string | null;
            impersonating: boolean;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
