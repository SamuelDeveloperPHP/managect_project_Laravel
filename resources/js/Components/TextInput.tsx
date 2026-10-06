import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

export default forwardRef(function TextInput(
    {
        type = 'text',
        className = '',
        isFocused = false,
        ...props
    }: InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'min-h-12 rounded-xl border-neutral-300 bg-white px-3.5 text-[15px] text-neutral-900 shadow-sm transition placeholder:text-neutral-400 hover:border-neutral-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 ' +
                className
            }
            ref={localRef}
        />
    );
});
