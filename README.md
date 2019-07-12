# Módulo ASAP Log para Prestashop 1.5 ou superior

Com este módulo você poderá oferecer o frete de forma nativa em sua loja. Basta instalar e configurar sua chave de integração.

**Importante:** Caso o CEP do destinatário não seja atendido, o peso total da cotação exceda 30kg ou o valor da cotação exceda o de sua negociação, a cotação não será feita.

## Instalação

### Baixando arquivo ZIP

Baixe o módulo (https://github.com/asaplog/prestashop/archive/master.zip) em seu servidor.

Na pasta do Pestashop, abra a pasta ```modules``` e extraia a pasta ```asaplog``` de dentro do arquivo zip.

## Pós instalação

Acesse a administração de sua loja e vá em MELHORAR > Módulos > Catálogo de Módulos.

Procure por ASAP Log e clique e Instalar e depois em Configurar.

Nesta tela, você deve preencher o código de integração fornecido no painel do cliente e salvar.

Tudo pronto! Todas as cotação serão calculados pela ASAP Log.

## Monitoramento

Você pode habilitar a opção **Registrar chamadas** na tela de configuração e consultar o arquivo ```var/log/asaplog_cotacao.log``` para verificar se houveram erros na integração.
