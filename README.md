# 🎓 Gerenciador de Eventos Escolares

Sistema web desenvolvido para auxiliar instituições de ensino na **organização, divulgação e inscrição de eventos escolares**.  
O projeto foi criado como parte do curso técnico em Desenvolvimento de Sistemas da ETEC de Bragança Paulista, com foco em praticar conceitos de **desenvolvimento full stack**.

---

## 🚀 Funcionalidades Principais

- Cadastro, edição e exclusão de eventos  
- Inscrição de alunos e professores nos eventos  
- Listagem de eventos com informações detalhadas  
- Geração de relatórios e certificados em **PDF**  
- Painel administrativo para controle e gestão  

---

## 🛠️ Tecnologias Utilizadas

- **PHP** — Back-end e lógica de negócios  
- **MySQL** — Banco de dados relacional  
- **HTML5** — Estrutura das páginas  
- **CSS3** — Estilização e responsividade  
- **JavaScript** — Interatividade e validações  
- **Bootstrap** — Layout responsivo e componentes visuais  
- **Composer** — Gerenciador de dependências (utilizado para bibliotecas de PDF)

---

## 🗃️ Banco de Dados

O arquivo de banco de dados (`bd_eventosescolares.sql`) está localizado na pasta `/database`.  
> Basta importá-lo no **phpMyAdmin** para criar as tabelas necessárias.

---

## 📦 Instalação e Execução

1. Clone este repositório:
   ```bash
   git clone https://github.com/SEU_USUARIO/Gerenciador-de-Eventos-Escolares.git

2. Acesse a pasta do projeto:
   ```bash
   cd Gerenciador-de-Eventos-Escolares

3. Instale as dependências via Composer:
    ```bash
    composer install

4. Configure a conexão com o banco de dados no arquivo de configuração (ex: config.php ou similar).

5. Importe o banco de dados (banco_de_dados.sql) no phpMyAdmin.

6. Inicie o servidor local com o XAMPP (ou similar) e acesse:
    ```bash
    http://localhost/Gerenciador-de-Eventos-Escolares

## 📚 Aprendizados

Durante o desenvolvimento, foi possível aplicar conceitos de:

  * CRUD em PHP e MySQL

  * Estruturação de layout com Bootstrap

  * Geração de relatórios automáticos em PDF

  * Organização de código e versionamento com Git

  * Integração entre front-end e back-end

## 👩‍💻 Autora

**Giulia Acsa dos Santos Muniz**
Estudante do curso técnico em Desenvolvimento de Sistemas — ETEC de Bragança Paulista

📫 LinkedIn: 
www.linkedin.com/in/giulia-acsa-dos-santos-muniz-b5bb13267

## ⚙️ Observação

A pasta /vendor foi ignorada no repositório por meio do .gitignore,
mas pode ser recriada automaticamente executando o comando:
   ```bash
   composer install
