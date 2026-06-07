# 4.2 PhreeForm (report + form engine)

Bizuno's reporting and document-rendering layer. PhreeForm is two things in
one: a **report engine** (tabular, with grouping/totaling, output to HTML/PDF)
and a **form engine** (visual layout — invoices, packing slips, statements,
anything pixel-positioned). Both share the same data-binding system.

Since 7.3.9, PDF output uses [tFPDF](https://www.fpdf.org/en/script/script92.php)
exclusively (TCPDF was removed). Barcodes come from `picqer/php-barcode-generator`;
basic inline HTML in cells comes from an in-tree shim.

## Pages

| #  | Page                                                                  | Status | Audience       |
|----|-----------------------------------------------------------------------|--------|----------------|
| 01 | [Report engine overview](./01-report-engine-overview.md)              | published | bookkeeper, admin |
| 02 | [Form designer](./02-form-designer.md)                                | published | admin          |
| 03 | [Data binding and fields](./03-data-binding-and-fields.md)            | published | admin, developer |
| 04 | [Processors and formatters](./04-processors-and-formatters.md)        | published | admin, developer |
| 05 | [Custom forms](./05-custom-forms.md)                                  | published | admin          |
