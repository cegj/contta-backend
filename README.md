<div align="center">
  <img src="https://camo.githubusercontent.com/49f2575771d194651c7e8b11caf3702cbe97ab512c8423930ae54aa99f9fef0b/68747470733a2f2f696d672e736869656c64732e696f2f7374617469632f76313f7374796c653d666f722d7468652d6261646765266d6573736167653d4c61726176656c26636f6c6f723d464632443230266c6f676f3d4c61726176656c266c6f676f436f6c6f723d464646464646266c6162656c3d" alt="Laravel" />
  <img src="https://camo.githubusercontent.com/043cf178670996a77ee676c08ffebc44661909c10e09c07a12a287cab3f8e548/68747470733a2f2f696d672e736869656c64732e696f2f7374617469632f76313f7374796c653d666f722d7468652d6261646765266d6573736167653d50485026636f6c6f723d373737424234266c6f676f3d504850266c6f676f436f6c6f723d464646464646266c6162656c3d" alt="PHP" />
  <img src="https://camo.githubusercontent.com/539a184961e9ab46a914b3a57718cd52f9a122ffb33a0bcaaa92484add20ba72/68747470733a2f2f696d672e736869656c64732e696f2f7374617469632f76313f7374796c653d666f722d7468652d6261646765266d6573736167653d4d7953514c26636f6c6f723d343437394131266c6f676f3d4d7953514c266c6f676f436f6c6f723d464646464646266c6162656c3d" alt="MySQL" />
  <img src="https://camo.githubusercontent.com/9c2f1381d03b23626b66eb3372afe109aa0be6b50d1695c9ca939289290e39a7/68747470733a2f2f696d672e736869656c64732e696f2f7374617469632f76313f7374796c653d666f722d7468652d6261646765266d6573736167653d4a534f4e26636f6c6f723d303030303030266c6f676f3d4a534f4e266c6f676f436f6c6f723d464646464646266c6162656c3d" alt="JSON" />
  <img src="https://camo.githubusercontent.com/0d7baa31f8240f8594bbcf5df27410c0986455d8c46222f05099a62fa957c31b/68747470733a2f2f696d672e736869656c64732e696f2f7374617469632f76313f7374796c653d666f722d7468652d6261646765266d6573736167653d4a534f4e2b5765622b546f6b656e7326636f6c6f723d303030303030266c6f676f3d4a534f4e2b5765622b546f6b656e73266c6f676f436f6c6f723d464646464646266c6162656c3d" alt="JSON WEB TOKEN" />
</div>

# Contta v2-beta (backend)

Criado por [mim](https://github.com/cegj), com **Laravel/PHP**, para fins de aprendizado e uso pessoal.

Este é o repositório do backend do projeto [Contta](https://github.com/cegj/contta-frontend), desenvolvido com **Laravel/PHP**. A interação é realizada por meio de **requisições HTTP** nos diversos verbos (get, post, delete, patch) e as respostas são devolvidas em **JSON**. A autenticação (nos endpoints que exigem) é feita via **Json Web Token**. 

O projeto está estruturado seguindo a arquitetura **MVC**, e as requisições **SQL** dos *models* utilizam o **Eloquent** do Laravel.

## Stack

- Laravel/PHP;
- Json Web Token;
- MySQL.

Frontend desenvolvido em React: [veja o repositório aqui](https://github.com/cegj/contta-frontend).

## Recursos

1. Endpoints para obter (GET) transações, saldos, contas, categorias, dados de orçamento e outras informações via HTTP request;
2. Endpoints para criar (POST) transações, contas, categorias e outras informações via HTTP request;
3. Endpoints para editar (PATCH) transações, contas e categorias via HTTP request;
4. Endpoints para apagar (DELETE) transações, contas e categorias via HTTP request;
5. Os endpoints utilizam autenticação via Json Web Token;
6. Interação com banco de dados MySQL via Laravel Eloquent.

## Exemplo de respostas

Endpoint GET para obter transações em um determinado mês: 
```
{
    "message":"Trabnsações obtidas de 2023-12-01 até 2023-12-31"
    "transactions": [
        {
            "id":231,
            "transaction_date":"2023-12-01",
            "payment_date":"2023-12-01",
            "type":"R",
            "value":631507,
            "description":"Salário",
            "category_id":43,
            "account_id":5,
            "user_id":1,
            "preview":1,
            "usual":0,
            "budget_control":0,
            "transfer_key":null,
            "installments_key":"616744967697",
            "installment":11,
            "created_at":"2023-01-23T20:59:29.000000Z",
            "updated_at":"2023-07-31T22:40:35.000000Z",
            "account":{
                "id":5,
                "name":"Banco do Brasil",
                "type":"Conta Bancária",
                "initial_balance":3883,
                "show":1,
                "created_at":"2023-01-23T02:42:25.000000Z",
                "updated_at":"2023-01-24T02:18:58.000000Z"
                },
                "category":{
                    "id":43,
                    "name":"Salário",
                    "group_id":8,
                    "created_at":"2023-01-23T02:40:00.000000Z",
                    "updated_at":"2023-01-23T02:40:00.000000Z"
                },
        }
    ]
}
```

Endpoint GET para buscar lista de contas:
```
{
    "message":"Contas recuperadas com sucesso"
    "accounts":[
        {
            "id":1,
            "name":"Itaucard Visa",
            "type":"Cartão de crédito",
            "initial_balance":0,
            "show":1,
            "created_at":"2023-01-22T23:41:37.000000Z",
            "updated_at":"2023-03-22T23:14:27.000000Z"
        },
        {
            "id":2,
            "name":"Banco do Brasil",
            "type":"Conta bancária",
            "initial_balance":3883,
            "show":1,
            "created_at":"2023-01-22T23:41:49.000000Z",
            "updated_at":"2023-07-14T20:36:02.000000Z"
        }
        ]
}
```

Endpoint POST registrar uma nova transação:
```
{
    "message":"Transação registrada com sucesso",
    "transactions":[
        {
            "transaction_date":"2023-10-02",
            "payment_date":"2023-10-02",
            "type":"D","value":-12560,
            "description":"Compras",
            "category_id":null,
            "account_id":1,
            "user_id":1,
            "preview":false,
            "usual":false,
            "budget_control":false,
            "installments_key":null,
            "installment":1,
            "updated_at":"2023-10-02T18:17:23.000000Z",
            "created_at":"2023-10-02T18:17:23.000000Z","id":2008
        }
    ]
}
```

## Imagens e links para navegação (do frontend)

<a href="https://imgur.com/OmRMs5Y"><img src="https://i.imgur.com/OmRMs5Y.png" title="Entrada (login)" /></a>

<a href="https://imgur.com/ihEp45i"><img src="https://i.imgur.com/ihEp45i.png" title="Painel (board)" /></a>

<a href="https://imgur.com/i3BJ8Mv"><img src="https://i.imgur.com/i3BJ8Mv.png" title="Extrato (statement)" /></a>

<a href="https://imgur.com/KFLKwaL"><img src="https://i.imgur.com/KFLKwaL.png" title="Orçamento (budget table)" /></a>

<a href="https://imgur.com/YEl3FhD"><img src="https://i.imgur.com/YEl3FhD.png" title="Categorias (categories)" /></a>

<a href="https://imgur.com/isJSPSC"><img src="https://i.imgur.com/isJSPSC.png" title="Menu principal (main menu)" /></a>

<a href="https://imgur.com/rXbyWmK"><img src="https://i.imgur.com/rXbyWmK.png" title="Registro de transação (transaction input)" /></a>

<a href="https://imgur.com/kHwwcfk"><img src="https://i.imgur.com/kHwwcfk.png" title="Configurações (settings)" /></a>