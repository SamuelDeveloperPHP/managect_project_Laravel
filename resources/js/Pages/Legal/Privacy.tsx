import LegalLayout, { ContactLine, LegalProps, Section } from '@/Layouts/LegalLayout';
import { Head, Link } from '@inertiajs/react';

export default function Privacy(props: LegalProps) {
    const { legal } = props;

    return (
        <LegalLayout title="Política de Privacidade" version={legal.version}>
            <Head title="Política de Privacidade" />

            <p>Esta política explica quais dados pessoais o Trilha+ trata, para quê, por quanto tempo e como você exerce seus direitos, conforme a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018).</p>

            <Section title="1. Quem é o responsável">
                <p><strong>{legal.controller}</strong> oferece o Trilha+ e é a controladora dos dados de cadastro e de acesso descritos abaixo. {legal.dpo_name && <>O encarregado pelo tratamento de dados (DPO) é <strong>{legal.dpo_name}</strong>. </>}Para falar sobre seus dados, escreva para <ContactLine {...props} />.</p>
                <p>Os projetos, backlogs, tarefas e arquivos que a sua empresa registra no sistema pertencem à empresa. Em relação a esse conteúdo, a empresa é a controladora e o Trilha+ atua como operador, tratando os dados apenas para prestar o serviço contratado.</p>
            </Section>

            <Section title="2. Dados que tratamos">
                <ul>
                    <li><strong>Cadastro da empresa:</strong> nome ou razão social, CNPJ ou CPF do titular da conta e, se informado, um segundo e-mail de recuperação.</li>
                    <li><strong>Cadastro de cada pessoa:</strong> nome, e-mail de acesso, CPF (informado no cadastro do administrador e, opcionalmente, dos demais usuários), perfil e permissões, e foto de perfil (opcional).</li>
                    <li><strong>Segurança da conta:</strong> senha (guardada apenas em forma irreversível), segredo e códigos de recuperação da verificação em duas etapas (guardados criptografados), data do último acesso.</li>
                    <li><strong>Registros de atividade:</strong> data e hora, ação realizada, resultado, endereço IP e navegador usado.</li>
                    <li><strong>Conteúdo da empresa:</strong> projetos, backlogs, tarefas, responsáveis, anexos e demais informações que a empresa inserir.</li>
                    <li><strong>Ciência desta política:</strong> versão aceita e data do aceite.</li>
                </ul>
                <p>Não tratamos dados pessoais sensíveis e não usamos seus dados para publicidade nem para decisões automatizadas.</p>
            </Section>

            <Section title="3. Para que usamos e em que base legal">
                <ul>
                    <li><strong>Prestar o serviço e manter sua conta</strong> (criar o acesso, autenticar, isolar os dados de cada empresa): execução de contrato, art. 7º, V.</li>
                    <li><strong>Guardar registros de acesso</strong> pelo prazo exigido em lei: cumprimento de obrigação legal, art. 7º, II (Marco Civil da Internet, art. 15).</li>
                    <li><strong>Segurança, prevenção a fraudes e auditoria</strong> (registro de atividade, bloqueio de tentativas abusivas, verificação em duas etapas, cópias de segurança): legítimo interesse, art. 7º, IX.</li>
                    <li><strong>Comunicações do serviço</strong> (recuperação de senha, avisos de segurança): execução de contrato.</li>
                </ul>
            </Section>

            <Section title="4. Com quem compartilhamos">
                <p>Os administradores da sua empresa veem os dados dos usuários dela (nome, e-mail, perfil, último acesso e registros de atividade). Nenhuma empresa enxerga os dados de outra.</p>
                <p>Usamos prestadores que tratam dados em nosso nome, apenas para operar o serviço: hospedagem e infraestrutura, envio de e-mails do sistema, armazenamento das cópias de segurança e entrega das fontes de texto (Bunny Fonts, que recebe o endereço IP de quem abre as páginas para entregar as fontes, sem cookies). Também podemos informar dados a autoridades quando a lei exigir. <strong>Não vendemos dados pessoais.</strong></p>
                <p>Se algum prestador armazenar dados fora do Brasil, a transferência seguirá as hipóteses e salvaguardas do art. 33 da LGPD.</p>
            </Section>

            <Section title="5. Por quanto tempo guardamos">
                <ul>
                    <li><strong>Conta:</strong> enquanto estiver ativa. Quando você pede a exclusão, seus dados pessoais são apagados (anonimizados) e o histórico dos projetos permanece sem identificar você.</li>
                    <li><strong>Registros de atividade:</strong> o endereço IP e o navegador são removidos após {legal.ip_retention_days} dias; o registro em si é apagado após {legal.audit_retention_days} dias.</li>
                    <li><strong>Cópias de segurança:</strong> mantidas por até {legal.backup_keep_days} dias. Dados já eliminados do sistema podem existir nessas cópias até elas expirarem, e não são usados para outra finalidade.</li>
                </ul>
            </Section>

            <Section title="6. Seus direitos">
                <p>Você pode, a qualquer momento (LGPD, art. 18): confirmar que tratamos seus dados, acessá-los, corrigi-los, pedir a anonimização ou eliminação, levar seus dados para outro serviço (portabilidade), saber com quem compartilhamos e revogar consentimentos dados.</p>
                <ul>
                    <li><strong>Acessar e levar seus dados:</strong> em <Link href={route('profile.edit')}>Perfil</Link>, use &ldquo;Baixar meus dados&rdquo; (arquivo JSON).</li>
                    <li><strong>Corrigir:</strong> altere nome, e-mail e foto em Perfil.</li>
                    <li><strong>Apagar:</strong> em Perfil, use &ldquo;Excluir conta e apagar meus dados&rdquo;. O administrador da empresa também pode fazer isso a seu pedido.</li>
                    <li><strong>Qualquer outro pedido ou dúvida:</strong> <ContactLine {...props} />. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</li>
                </ul>
            </Section>

            <Section title="7. Como protegemos">
                <p>Conexão criptografada (HTTPS), senhas com hash, verificação em duas etapas por aplicativo autenticador, isolamento dos dados por empresa, controle de permissões por perfil, limite de tentativas de acesso, registro de atividade e cópias de segurança criptografadas. Nenhum sistema é totalmente imune; em caso de incidente que possa causar risco relevante, comunicaremos os titulares e a ANPD, nos termos do art. 48 da LGPD.</p>
            </Section>

            <Section title="8. Cookies">
                <p>Usamos apenas cookies essenciais ao funcionamento: o da sessão (mantém você conectado) e o de proteção contra falsificação de requisições (CSRF). Não usamos cookies de publicidade nem de análise de comportamento.</p>
            </Section>

            <Section title="9. Mudanças nesta política">
                <p>Quando o texto mudar de forma relevante, publicamos uma nova versão e pedimos que você leia e aceite de novo no próximo acesso. A versão em vigor é a <strong>{legal.version}</strong>.</p>
            </Section>
        </LegalLayout>
    );
}
