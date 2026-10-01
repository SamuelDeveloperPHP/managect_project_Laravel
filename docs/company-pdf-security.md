# Segurança dos documentos PDF da empresa

Os documentos são gravados no disco privado do Laravel (`storage/app/private`), com nome aleatório, hash SHA-256 e vínculo à empresa e ao usuário que enviou. O download exige sessão e escopo da empresa e sempre usa `Content-Disposition: attachment` e `X-Content-Type-Options: nosniff`.

O servidor valida extensão/MIME, assinatura `%PDF-`, tamanho máximo de 10 MB e então exige dois verificadores locais. Sem ambos os verificadores, o envio falha fechado e nenhum arquivo é guardado:

- `PDF_VALIDATOR_BINARY`: executável local confiável que recebe o caminho temporário como único argumento e analisa a estrutura completa do PDF. Deve rejeitar arquivos malformados, criptografados, com formulários AcroForm/XFA ou conteúdo ativo (por exemplo JavaScript, ações automáticas, anexos embutidos e Launch). Em sucesso, deve retornar JSON em stdout no formato `{"valid":true,"has_forms":false,"active_content":false,"encrypted":false}` e código de saída zero. Campo ausente, saída inválida ou resultado positivo de risco rejeita o arquivo.
- `ANTIVIRUS_BINARY`: executável local de antivírus compatível com a interface do ClamAV (`--no-summary --stdout -- <arquivo>`). Código de saída diferente de zero rejeita o arquivo.

Configure os caminhos absolutos no ambiente do servidor e valide manualmente os dois executáveis antes de habilitar o recebimento. O validador estrutural deve ser mantido e atualizado pelo operador; o aplicativo não tenta substituir análise de PDF por expressões regulares ou apenas pela assinatura do arquivo. O ambiente de desenvolvimento atual não tem esses verificadores, portanto a área de envio está intencionalmente bloqueada até que eles sejam instalados e configurados.
