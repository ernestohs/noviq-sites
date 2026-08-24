import csv
import textwrap
import os
from PIL import Image, ImageDraw, ImageFont

# Configuration
TEMPLATE_PATH = "watermarked_img_5660576294528698116.jpg"
CSV_PATH = "tests_data.csv"
OUTPUT_DIR = "generated_test_images"

# March Analytics Brand Colors
COLOR_BLACK = "#121212"
COLOR_GREEN = "#16A34A"
COLOR_GRAY = "#222222"

def get_linux_font():
    """Scans standard Linux directories for a usable TrueType font."""
    common_fonts = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
        "/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf",
        "/usr/share/fonts/ubuntu/Ubuntu-B.ttf"
    ]
    
    for font_path in common_fonts:
        if os.path.exists(font_path):
            return font_path
            
    # Hard fail if no scalable font is found, rather than creating microscopic text
    raise FileNotFoundError(
        "CRITICAL: Could not find a standard TrueType font on your system. "
        "Run 'sudo apt install fonts-dejavu' in your terminal, then run this script again."
    )

def generate_images_from_csv():
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    if not os.path.exists(TEMPLATE_PATH):
        print(f"Error: Could not find {TEMPLATE_PATH}")
        return

    base_image = Image.open(TEMPLATE_PATH)
    
    # Automatically grab a valid Linux font to ensure proper scaling
    font_path = get_linux_font()
    font_title = ImageFont.truetype(font_path, 110)
    font_details = ImageFont.truetype(font_path, 55)

    # Read CSV and generate images
    with open(CSV_PATH, mode='r', encoding='utf-8') as file:
        reader = csv.DictReader(file)
        
        for row in reader:
            test_name = row['Test Name'].strip().upper() 
            details = row['Details'].strip()
            
            img = base_image.copy()
            draw = ImageDraw.Draw(img)

            # 1. Draw Title (Top Center)
            title_bbox = draw.textbbox((0, 0), test_name, font=font_title)
            title_width = title_bbox[2] - title_bbox[0]
            title_x = (img.width - title_width) / 2
            title_y = img.height * 0.05 
            
            draw.text((title_x, title_y), test_name, fill=COLOR_BLACK, font=font_title)

            # 2. Draw Accent Line (Directly beneath title)
            line_y = title_y + (title_bbox[3] - title_bbox[1]) + 30
            line_width = 250 
            line_x_start = (img.width - line_width) / 2
            
            draw.line(
                [(line_x_start, line_y), (line_x_start + line_width, line_y)], 
                fill=COLOR_GREEN, 
                width=8 
            )

            # 3. Draw Details (Bottom Center Whitespace)
            wrapped_details = textwrap.fill(details, width=45)
            lines = wrapped_details.split('\n')
            
            # Calculate total text block height to center it in the bottom section
            line_spacing = 20
            total_text_height = sum([
                draw.textbbox((0, 0), line, font=font_details)[3] - draw.textbbox((0, 0), line, font=font_details)[1] 
                for line in lines
            ]) + (line_spacing * (len(lines) - 1))
            
            # Anchor at 82% down the image
            details_y = (img.height * 0.82) - (total_text_height / 2) 
            
            for line in lines:
                line_bbox = draw.textbbox((0, 0), line, font=font_details)
                line_width = line_bbox[2] - line_bbox[0]
                line_x = (img.width - line_width) / 2
                
                draw.text((line_x, details_y), line, fill=COLOR_GRAY, font=font_details)
                details_y += (line_bbox[3] - line_bbox[1]) + line_spacing

            # Save Output
            safe_filename = "".join([c for c in test_name if c.isalpha() or c.isdigit() or c==' ']).rstrip()
            output_filename = f"{safe_filename.replace(' ', '_').lower()}.jpg"
            output_path = os.path.join(OUTPUT_DIR, output_filename)
            
            img.save(output_path, quality=95)
            print(f"Generated: {output_filename}")

if __name__ == "__main__":
    generate_images_from_csv()

