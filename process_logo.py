from PIL import Image
import base64
import io

def process():
    # Open the image
    img = Image.open(r'd:\projects\uni-activity\public\logo.png').convert('RGBA')

    # Get data
    datas = img.getdata()

    # Create new data replacing white with transparent
    newData = []
    # Using a threshold of 240 to catch near-white pixels (anti-aliasing artifacts on white background)
    for item in datas:
        if item[0] > 240 and item[1] > 240 and item[2] > 240:
            newData.append((255, 255, 255, 0))
        else:
            newData.append(item)

    # Update image data
    transparent_img = Image.new('RGBA', img.size)
    transparent_img.putdata(newData)
    
    # Save as PNG
    png_path = r'd:\projects\uni-activity\public\logo_transparent.png'
    transparent_img.save(png_path, 'PNG')

    # Convert to Base64 for SVG
    buffered = io.BytesIO()
    transparent_img.save(buffered, format='PNG')
    img_str = base64.b64encode(buffered.getvalue()).decode()

    svg_content = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {transparent_img.width} {transparent_img.height}">
  <image width="{transparent_img.width}" height="{transparent_img.height}" href="data:image/png;base64,{img_str}"/>
</svg>'''

    with open(r'd:\projects\uni-activity\public\logo.svg', 'w') as f:
        f.write(svg_content)
        
    print('New Logo processed successfully with color keying (white to transparent)!')

if __name__ == '__main__':
    process()
