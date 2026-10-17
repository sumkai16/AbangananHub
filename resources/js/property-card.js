// Photo carousel of a listing card (components/property-card.blade.php).
//
// The server renders only the first photo. The rest are URLs in the card's data-photos attribute and become
// <img>s the first time the visitor touches the card (hover, touch, focus, arrow click), so a page of 30 cards
// downloads 30 photos, not 300. Cards swapped in later (live filter, AI results) get this for free:
// Alpine initialises them like any other markup.
//
// data-photos is a JSON array of URLs: "…w={w}…" templates when the photo can be resized (see
// App\Support\Images), plain URLs when it can't. data-widths is Images::SRCSET_WIDTHS (comma list), and
// the fallback `src` uses WIDTH, so the sizes stay defined in PHP and the card markup.
const WIDTH = 640;

export default () => ({
    activeSlide: 0,
    slides: 1,
    extra: [],

    init() {
        try {
            this.photos = JSON.parse(this.$el.dataset.photos || '[]');
        } catch {
            this.photos = [];
        }
        this.widths = (this.$el.dataset.widths || '').split(',').filter(Boolean);
        this.slides = this.photos.length + 1;
    },

    load() {
        if (this.extra.length || this.photos.length === 0) return;

        this.extra = this.photos.map((url) => {
            if (!url.includes('{w}')) return { src: url, srcset: null };

            return {
                src: url.replace('{w}', WIDTH),
                srcset: this.widths.map((w) => `${url.replace('{w}', w)} ${w}w`).join(', '),
            };
        });
    },

    prev() {
        this.load();
        this.activeSlide = this.activeSlide > 0 ? this.activeSlide - 1 : this.slides - 1;
    },

    next() {
        this.load();
        this.activeSlide = this.activeSlide < this.slides - 1 ? this.activeSlide + 1 : 0;
    },
});
