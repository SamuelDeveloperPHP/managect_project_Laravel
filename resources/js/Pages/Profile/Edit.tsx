import { PageHeader, panelClass } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import PrivacyForm from './Partials/PrivacyForm';
import TwoFactorForm from './Partials/TwoFactorForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    twoFactor,
    privacy,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; privacy: { terms_version: string | null; terms_accepted_at: string | null }; twoFactor: { enabled: boolean; required: boolean; recovery_codes_left: number; setup: { qr_svg: string; secret: string } | null } }>) {
    return (
        <AuthenticatedLayout
            header={<PageHeader title="Perfil" meta="Seus dados de acesso e segurança" />}
        >
            <Head title="Perfil" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className={`${panelClass} p-6 sm:p-8`}>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className={`${panelClass} p-6 sm:p-8`}>
                        <TwoFactorForm twoFactor={twoFactor} />
                    </div>

                    <div className={`${panelClass} p-6 sm:p-8`}>
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className={`${panelClass} p-6 sm:p-8`}>
                        <PrivacyForm privacy={privacy} />
                    </div>

                    <div className={`${panelClass} p-6 sm:p-8`}>
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
