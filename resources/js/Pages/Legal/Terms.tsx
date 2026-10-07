import LegalLayout, { ContactLine, LegalProps, Section } from '@/Layouts/LegalLayout';
import { Head, Link } from '@inertiajs/react';

export default function Terms(props: LegalProps) {
    const { legal } = props;

    return (
        <LegalLayout title="Termos de Uso" version={legal.version}>
            <Head title="Termos de Uso" />

            <p>Estes termos regem o uso do Trilha+, oferecido por <strong>{legal.controller}</strong>. Ao fazer o cadastro ou continuar usando o sistema, você declara que leu e concorda com eles e com a <Link href={route('legal.privacy')} className="font-semibold text-brand-700 underline">Política de Privacidade</Link>.</p>

            <Section title="1. O serviço">
                <p>O Trilha+ é uma ferramenta de gestão de projetos: cadastro de projetos, backlogs e cronograma (Gantt), com cada empresa em um ambiente próprio e acesso controlado por perfil.</p>
            </Section>

            <Section title="2. Conta e acesso">
                <ul>
                    <li>O acesso é pessoal e feito por e-mail e senha. Não compartilhe sua senha nem os códigos da verificação em duas etapas.</li>
                    <li>Cada empresa tem um administrador, responsável por incluir e remover usuários e definir permissões.</li>
                    <li>As informações do cadastro devem ser verdadeiras e atualizadas. Você é responsável pelas ações feitas com a sua conta.</li>
                    <li>Avise imediatamente se suspeitar de uso indevido da sua conta.</li>
                </ul>
            </Section>

            <Section title="3. Uso adequado">
                <p>É proibido tentar acessar dados de outras empresas, burlar controles de segurança, sobrecarregar o serviço, inserir conteúdo ilícito ou código malicioso, ou usar o sistema para violar direitos de terceiros. Podemos suspender contas que descumpram estas regras.</p>
            </Section>

            <Section title="4. Conteúdo da empresa">
                <p>Os projetos, tarefas e arquivos inseridos pertencem à empresa, que responde por ter o direito de usá-los e por tratar legalmente os dados pessoais de terceiros que incluir. Tratamos esse conteúdo apenas para prestar o serviço.</p>
            </Section>

            <Section title="5. Disponibilidade e cópias de segurança">
                <p>Trabalhamos para manter o serviço disponível e fazemos cópias de segurança periódicas, mas podem ocorrer indisponibilidades para manutenção ou por fatores externos. Recomendamos que a empresa mantenha as próprias cópias do que for crítico.</p>
            </Section>

            <Section title="6. Responsabilidade">
                <p>O serviço é fornecido no estado em que se encontra. Na extensão permitida em lei, não respondemos por danos indiretos nem por perdas decorrentes de uso indevido da conta, de informação incorreta inserida pela empresa ou de eventos fora do nosso controle.</p>
            </Section>

            <Section title="7. Encerramento">
                <p>Você pode apagar a sua conta e os seus dados pessoais a qualquer momento em Perfil, e a empresa pode encerrar o uso do serviço. Podemos encerrar o acesso em caso de descumprimento destes termos.</p>
            </Section>

            <Section title="8. Mudanças e contato">
                <p>Podemos atualizar estes termos; quando a mudança for relevante, pediremos um novo aceite. A versão em vigor é a <strong>{legal.version}</strong>. Dúvidas: <ContactLine {...props} />. Aplica-se a lei brasileira.</p>
            </Section>
        </LegalLayout>
    );
}
