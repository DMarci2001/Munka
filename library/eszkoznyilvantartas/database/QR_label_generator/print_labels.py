from pathlib import Path
from PIL import Image, ImageChops, ImageDraw, ImageFont
import pandas as pd
import qrcode

LABEL_W = 900
LABEL_H = 260
BG = "white"
MARGIN = 25
GAP = 30

TEXT_H_RESERVE = 45
QR_SIZE = LABEL_H - 2 * MARGIN - TEXT_H_RESERVE
QR_X = MARGIN
QR_Y = MARGIN

TEXT_Y = QR_Y + QR_SIZE + 8
TEXT_COLOR = "#2f3b52"

LOGO_PATH = Path("C:/Users/dugal/Downloads/HMM_final_logo.png")
LOGO_ZONE_X0 = QR_X + QR_SIZE + GAP
LOGO_ZONE_X1 = LABEL_W - MARGIN
LOGO_ZONE_H = LABEL_H - 2 * MARGIN

SCRIPT_DIR = Path(__file__).resolve().parent
EXCEL_PATH = SCRIPT_DIR / "qr_batch_import_template_localhost_18(1).xlsx"
SHEET_NAME = "QR Import"

OUTPUT_DIR = SCRIPT_DIR / "output_labels"
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

def fit_logo(logo_path, target_h, max_w):
    logo = Image.open(logo_path).convert("RGBA")

    rgb = logo.convert("RGB")
    diff = ImageChops.difference(rgb, Image.new("RGB", rgb.size, (255, 255, 255)))
    bbox = diff.getbbox()
    if bbox:
        pad = int(0.04 * max(bbox[2] - bbox[0], bbox[3] - bbox[1]))
        l, t, r, b = bbox
        l = max(0, l - pad)
        t = max(0, t - pad)
        r = min(logo.width, r + pad)
        b = min(logo.height, b + pad)
        logo = logo.crop((l, t, r, b))

    ratio = min(target_h / logo.height, max_w / logo.width)
    w, h = int(logo.width * ratio), int(logo.height * ratio)
    return logo.resize((w, h))

df = pd.read_excel(EXCEL_PATH, sheet_name=SHEET_NAME)
logo = fit_logo(LOGO_PATH, LOGO_ZONE_H, LOGO_ZONE_X1 - LOGO_ZONE_X0)
LOGO_X = LOGO_ZONE_X0 + (LOGO_ZONE_X1 - LOGO_ZONE_X0 - logo.width) // 2
LOGO_Y = MARGIN + (LOGO_ZONE_H - logo.height) // 2

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