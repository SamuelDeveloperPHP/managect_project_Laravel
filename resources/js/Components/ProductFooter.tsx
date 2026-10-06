export default function ProductFooter({ className = '' }: { className?: string }) {
    return (
        <p className={`text-xs text-slate-500 ${className}`}>
            © {new Date().getFullYear()} Trilha+ · Um produto NexoCore Tecnologia
        </p>
    );
}
