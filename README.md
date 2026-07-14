<div align="center">

# 🧾 Moloni ON for WHMCS

**Turn your WHMCS orders into legal invoices in [Moloni ON](https://www.molonion.pt/) —
Portugal's cloud invoicing platform — without re-typing anything or missing a document.**

</div>

---

## ✨ What it does

- 🔐 **Connects WHMCS to your Moloni ON account** through a secure sign-in, and lets you
  choose which company to bill from.
- ⚡ **Creates invoices from your orders** — one at a time, in bulk, or fully automatically
  the moment a WHMCS invoice is marked as paid.
- 🪄 **Fills in the details for you** — customers, products, taxes, payment method and
  currency are read from each order and matched to (or created in) your Moloni ON account.
- 🇵🇹 **Keeps documents fiscally correct** — VAT rates, tax-exemption reasons, multi-currency
  conversion and Portuguese postcode formatting are all handled for you.
- 🎛️ **Keeps you in control** — download the official PDF, e-mail it to the customer, discard
  or revert an order, and review a full activity log of everything the module has done.

## ✅ Requirements

- 🟢 WHMCS **7.0 or newer**
- 🐘 PHP **7.4+** with the `curl`, `json` and `mbstring` extensions
- 🔑 A Moloni ON account with **API credentials** (API Client ID + Client Secret)

## 🚀 Installing

1. 📂 Copy the module into your WHMCS install at `modules/addons/moloni_on`.
2. 🧩 Activate **Moloni ON** under *Setup → Addon Modules*.
3. 🔓 Open it from *Addons → Moloni ON* and sign in to your Moloni ON account.

> 📖 The full step-by-step guide — including OAuth setup and the exact settings to choose —
> is in **[SETUP.md](SETUP.md)**.

## ⚙️ Configuring

Everything is configured from the module's **Settings** tab:

- 📄 default **document type** and **status**, and the **document set**
- 🧮 the **tax-exemption reason**
- 🤖 whether documents are created **automatically** when an invoice is paid
- 📧 whether they are **e-mailed** to the customer
- 📦 **product-mapping** defaults such as measurement unit and category (picked from
  dropdowns loaded live from your Moloni ON account)

> 💡 VAT is never a fixed setting — it is taken from **each order's own tax rate**. Any line
> that resolves to no VAT is automatically treated as tax-exempt, using the reason you choose.

## 📚 Documentation

- 🛠️ **[SETUP.md](SETUP.md)** — installation, OAuth, and deployment checklist
- 👩‍💻 **[DEV.md](DEV.md)** — project structure, building the module, and contributing

<div align="center">
<sub>Made for Portuguese hosting businesses running WHMCS 💙</sub>
</div>
