import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const photoInput = useRef<HTMLInputElement>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(user.profile_photo_url ?? null);
    const { data, setData, post, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
        photo: null as File | null,
        _method: 'patch' as const,
    });

    useEffect(() => {
        if (!data.photo) {
            setPhotoPreview(user.profile_photo_url ?? null);
            return;
        }

        const previewUrl = URL.createObjectURL(data.photo);
        setPhotoPreview(previewUrl);
        return () => URL.revokeObjectURL(previewUrl);
    }, [data.photo, user.profile_photo_url]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('profile.update'), {
            forceFormData: true,
            onSuccess: () => {
                setData('photo', null);
                if (photoInput.current) photoInput.current.value = '';
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Informações do perfil
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Atualize seu nome, endereço de e-mail e foto de perfil.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nome" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="E-mail" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="profile_photo" value="Foto de perfil" />
                    <div className="mt-2 flex flex-wrap items-center gap-4">
                        {photoPreview ? (
                            <img src={photoPreview} alt="Prévia da foto de perfil" className="h-16 w-16 rounded-full border border-slate-200 object-cover" />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-lg font-semibold text-indigo-700" aria-hidden="true">
                                {user.name.trim().charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <input
                                id="profile_photo"
                                ref={photoInput}
                                type="file"
                                accept=".jpeg,.jpg,.png,image/jpeg,image/png"
                                onChange={(event) => setData('photo', event.target.files?.[0] ?? null)}
                                className="block w-full cursor-pointer rounded-lg border border-slate-300 text-sm text-slate-600 file:me-4 file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                                aria-describedby="profile-photo-help"
                            />
                            <p id="profile-photo-help" className="mt-1 text-xs text-slate-500">Formatos aceitos: JPEG, JPG e PNG. Tamanho máximo: 2 MB.</p>
                        </div>
                    </div>
                    <InputError className="mt-2" message={errors.photo} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Seu endereço de e-mail ainda não foi verificado.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Clique aqui para reenviar o e-mail de verificação.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Um novo link de verificação foi enviado para seu e-mail.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>{processing ? 'Salvando...' : 'Salvar'}</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Salvo.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
