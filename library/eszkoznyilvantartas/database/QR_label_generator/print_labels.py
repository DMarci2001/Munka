from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import pandas as pd
import qrcode

LABEL_W = 900
LABEL_H = 260
BG = "white"

QR_SIZE = 118
QR_X = 125
QR_Y = 55

TEXT_Y = 182
TEXT_COLOR = "#2f3b52"

LOGO_PATH = Path("C:/Users/dugal/Downloads/hmm_logo_nagy.png")
LOGO_X = 345
LOGO_Y = 62
LOGO_W = 320

EXCEL_PATH = Path("C:/xampp/htdocs/eszkoznyilvantartas/database/QR_labels/qr_batch_import_template_localhost_18(1).xlsx")
SHEET_NAME = "QR Import"

OUTPUT_DIR = Path("C:/xampp/htdocs/eszkoznyilvantartas/database/QR_labels/output_labels")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

try:
    font = ImageFont.truetype("arial.ttf", 34)
except:
    font = ImageFont.load_default()

def make_qr(data, size):
    qr = qrcode.QRCode(
        version=None,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=10,
        border=1,
    )
    qr.add_data(str(data))
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white").convert("RGB")
    return img.resize((size, size))

def fit_logo(logo_path, target_w):
    logo = Image.open(logo_path).convert("RGBA")
    ratio = target_w / logo.width
    target_h = int(logo.height * ratio)
    return logo.resize((target_w, target_h))

df = pd.read_excel(EXCEL_PATH, sheet_name=SHEET_NAME)
logo = fit_logo(LOGO_PATH, LOGO_W)

for _, row in df.iterrows():
    qr_value = row.get("qr_value")
    asset_tag = str(row.get("asset_tag", "")).strip()
    label_text = str(row.get("label_text", "")).strip()

    if not qr_value or str(qr_value).strip() == "" or asset_tag == "":
        continue

    display_text = label_text if label_text else asset_tag

    label = Image.new("RGB", (LABEL_W, LABEL_H), BG)
    draw = ImageDraw.Draw(label)

    qr_img = make_qr(qr_value, QR_SIZE)
    label.paste(qr_img, (QR_X, QR_Y))

    bbox = draw.textbbox((0, 0), display_text, font=font)
    text_w = bbox[2] - bbox[0]
    text_x = QR_X + (QR_SIZE - text_w) // 2
    draw.text((text_x, TEXT_Y), display_text, fill=TEXT_COLOR, font=font)

    label.paste(logo, (LOGO_X, LOGO_Y), logo)

    out_path = OUTPUT_DIR / f"{asset_tag}.png"
    label.save(out_path)
    print("Saved:", out_path)