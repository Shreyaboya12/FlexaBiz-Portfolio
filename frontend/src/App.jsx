import { useEffect, useState } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import {
  BrowserRouter,
  Link,
  NavLink,
  Route,
  Routes,
  useParams,
} from 'react-router-dom';
import { apiRequest } from './lib/api';
import './App.css';

const endpoints = {
  hero: '/api/hero',
  videos: '/api/videos',
  services: '/api/services',
};

const sectionNames = {
  hero: 'hero slides',
  videos: 'videos',
  services: 'services',
};

const navItems = [
  { label: 'Home', to: '/' },
  { label: 'Videos', to: '/videos' },
  { label: 'Services', to: '/services' },
  { label: "Let's Talk", to: '/contact' },
];

const defaultHeroSlides = [
  {
    id: 'default-handbag',
    title: 'Ideas, made tangible.',
    subtitle: 'Premium visuals and creative experiences for modern brands.',
    image_url: '/hero/handbag.jpg',
  },
  {
    id: 'default-jewellery',
    title: 'Crafted to captivate.',
    subtitle: 'Thoughtful design and visual storytelling for brands with something to say.',
    image_url: '/hero/jewellery.jpg',
  },
  {
    id: 'default-sneakers',
    title: 'Every detail, in motion.',
    subtitle: 'Product stories brought to life through film and design.',
    video_url: '/hero/sneakers.mp4',
    image_url: '/hero/cosmetics.jpeg',
  },
  {
    id: 'default-cosmetics',
    title: 'Stories worth seeing.',
    subtitle: 'Premium visuals and creative experiences for modern brands.',
    image_url: '/hero/cosmetics.jpeg',
  },
];

function blankSection() {
  return { data: [], loading: true, error: '' };
}

function initialSections() {
  return Object.fromEntries(Object.keys(endpoints).map((key) => [key, blankSection()]));
}

async function loadSection(section, setSections) {
  setSections((current) => ({
    ...current,
    [section]: { ...current[section], loading: true, error: '' },
  }));
  try {
    const data = await apiRequest(endpoints[section]);
    if (!Array.isArray(data)) throw new Error(`The API returned an invalid ${sectionNames[section]} response.`);
    setSections((current) => ({ ...current, [section]: { data, loading: false, error: '' } }));
  } catch (error) {
    setSections((current) => ({
      ...current,
      [section]: {
        ...current[section],
        loading: false,
        error: error.message || `Could not load ${sectionNames[section]}.`,
      },
    }));
  }
}

function App() {
  return (
    <BrowserRouter>
      <PortfolioApp />
    </BrowserRouter>
  );
}

function PortfolioApp() {
  const [sections, setSections] = useState(initialSections);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [activeVideo, setActiveVideo] = useState(null);
  const [formState, setFormState] = useState({ status: 'idle', message: '', payload: null });

  useEffect(() => {
    Object.keys(endpoints).forEach((section) => loadSection(section, setSections));
  }, []);

  const submitEnquiry = async (payload) => {
    setFormState({ status: 'loading', message: '', payload });
    try {
      await apiRequest('/api/enquiries', { method: 'POST', body: JSON.stringify(payload) });
      setFormState({
        status: 'success',
        message: 'Thank you. Your enquiry has been received.',
        payload: null,
      });
      return true;
    } catch (error) {
      setFormState({
        status: 'error',
        message: error.message || 'Your enquiry could not be sent. Please try again.',
        payload,
      });
      return false;
    }
  };

  const handleEnquirySubmit = async (event) => {
    event.preventDefault();
    const formElement = event.currentTarget;
    const fields = new FormData(formElement);
    const payload = Object.fromEntries(fields.entries());
    if (await submitEnquiry(payload)) formElement.reset();
  };

  return (
    <div className="app-shell">
      <header className="site-header">
        <div className="container nav-container">
          <Link to="/" className="site-name" aria-label="FlexaBiz Portfolio home">FlexaBiz Portfolio</Link>
          <button
            type="button"
            className="menu-toggle"
            aria-label={mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'}
            aria-expanded={mobileMenuOpen}
            onClick={() => setMobileMenuOpen((open) => !open)}
          >
            <span />
            <span />
          </button>
          <nav className={`main-nav${mobileMenuOpen ? ' is-open' : ''}`} aria-label="Main navigation">
            {navItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
                onClick={() => setMobileMenuOpen(false)}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
        </div>
      </header>

      <main>
        <Routes>
          <Route
            path="/"
            element={(
              <HomePage
                sections={sections}
                onRetry={(section) => loadSection(section, setSections)}
                onPlayVideo={setActiveVideo}
              />
            )}
          />
          <Route
            path="/videos"
            element={(
              <VideosPage
                section={sections.videos}
                onRetry={() => loadSection('videos', setSections)}
                onPlayVideo={setActiveVideo}
              />
            )}
          />
          <Route
            path="/services"
            element={<ServicesPage section={sections.services} onRetry={() => loadSection('services', setSections)} />}
          />
          <Route path="/services/:id" element={<ServiceDetailPage section={sections.services} onRetry={() => loadSection('services', setSections)} />} />
          <Route
            path="/contact"
            element={(
              <ContactPage
                formState={formState}
                onSubmit={handleEnquirySubmit}
                onRetry={() => formState.payload && submitEnquiry(formState.payload)}
              />
            )}
          />
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>

      <footer className="site-footer">
        <div className="container footer-row">
          <div>
            <Link to="/" className="site-name footer-name">FlexaBiz Portfolio</Link>
            <p>Thoughtful creative work for ambitious brands.</p>
          </div>
          <nav className="footer-links" aria-label="Footer navigation">
            {navItems.map((item) => <Link key={item.to} to={item.to}>{item.label}</Link>)}
          </nav>
        </div>
        <div className="container footer-bottom"><span>FlexaBiz Portfolio</span><Link to="/contact">Start a conversation <span aria-hidden="true">↗</span></Link></div>
      </footer>

      <VideoModal video={activeVideo} onClose={() => setActiveVideo(null)} />
    </div>
  );
}

function SectionState({ section, onRetry, emptyMessage }) {
  if (section.loading) return <div className="state-panel" role="status">Loading content…</div>;
  if (section.error) {
    return (
      <div className="state-panel state-error" role="alert">
        <p>{section.error}</p>
        <button type="button" className="text-button" onClick={onRetry}>Try again <span aria-hidden="true">↗</span></button>
      </div>
    );
  }
  if (!section.data.length) return <div className="state-panel">{emptyMessage}</div>;
  return null;
}

function PageIntro({ eyebrow, title, description }) {
  return (
    <section className="page-intro container">
      <p className="eyebrow">{eyebrow}</p>
      <h1>{title}</h1>
      {description && <p className="page-description">{description}</p>}
    </section>
  );
}

function HomePage({ sections, onRetry, onPlayVideo }) {
  const featuredVideos = sections.videos.data.filter((item) => isFeatured(item)).slice(0, 3);
  const featuredServices = sections.services.data.filter((item) => isFeatured(item)).slice(0, 3);
  const introService = featuredServices.find((service) => service.image_url || service.image)
    || sections.services.data.find((service) => service.image_url || service.image);

  return (
    <>
      <HeroSlider section={sections.hero} onRetry={() => onRetry('hero')} />
      <section className="intro-band">
        <div className="container intro-band-inner">
          <div className="intro-copy">
            <p className="eyebrow">Independent creative studio</p>
            <h2>We bring ideas to life through considered design and compelling stories.</h2>
            <p>Explore product films and creative services shaped around the people and brands behind them.</p>
          </div>
          <figure className="intro-image">
            {introService
              ? <img src={introService.image_url || introService.image} alt={`${introService.name || introService.title || 'Creative'} service`} />
              : <div className="intro-image-empty" role="img" aria-label="Service image will appear when available" />}
          </figure>
        </div>
      </section>
      <PreviewSection
        eyebrow="Moving image"
        title="Product stories in motion."
        to="/videos"
        linkLabel="Explore all videos"
        section={sections.videos}
        onRetry={() => onRetry('videos')}
        emptyMessage="Featured videos will appear here when they're ready."
      >
        <div className="video-grid">
          {featuredVideos.map((video) => <VideoCard key={video.id} video={video} onPlay={onPlayVideo} />)}
        </div>
      </PreviewSection>
      <PreviewSection
        eyebrow="What we do"
        title="Creative support, shaped around your needs."
        to="/services"
        linkLabel="Explore services"
        section={sections.services}
        onRetry={() => onRetry('services')}
        emptyMessage="Services will appear here when they're ready."
      >
        <div className="service-grid">
          {featuredServices.map((service) => <ServiceCard key={service.id} service={service} />)}
        </div>
      </PreviewSection>
      <ContactCta />
    </>
  );
}

function PreviewSection({ eyebrow, title, to, linkLabel, section, onRetry, emptyMessage, children }) {
  const loadingOrError = section.loading || section.error;
  const hasFeatured = section.data.some((item) => isFeatured(item));
  return (
    <section className="content-section">
      <div className="container section-heading">
        <div><p className="eyebrow">{eyebrow}</p><h2>{title}</h2></div>
        <Link className="text-button section-link" to={to}>{linkLabel} <span aria-hidden="true">↗</span></Link>
      </div>
      <div className="container">
        {loadingOrError || !hasFeatured
          ? <SectionState section={{ ...section, data: hasFeatured ? section.data : [] }} onRetry={onRetry} emptyMessage={emptyMessage} />
          : children}
      </div>
    </section>
  );
}

function HeroSlider({ section, onRetry }) {
  const contentSlides = section.data.filter((slide) => slide && (
    heroImage(slide) || heroVideo(slide)
  ));
  const slides = contentSlides.length ? contentSlides : defaultHeroSlides;
  const [activeIndex, setActiveIndex] = useState(0);
  const prefersReducedMotion = useReducedMotion();
  const currentIndex = slides.length ? activeIndex % slides.length : 0;

  useEffect(() => {
    if (slides.length < 2 || prefersReducedMotion) return undefined;
    const interval = window.setInterval(() => setActiveIndex((index) => (index + 1) % slides.length), 4500);
    return () => window.clearInterval(interval);
  }, [slides.length, prefersReducedMotion]);

  const slide = slides[currentIndex];
  return (
    <section className="hero" aria-label="Featured work">
      <HeroBackdrop slide={slide} fallbackSlide={defaultHeroSlides[currentIndex % defaultHeroSlides.length]} prefersReducedMotion={prefersReducedMotion} />
      <HeroCopy slide={slide}>
        {section.error
          ? <p className="hero-status" role="alert">{section.error} <button type="button" onClick={onRetry}>Try again</button></p>
          : section.loading && <p className="hero-status" role="status">Loading featured work…</p>}
      </HeroCopy>
      {slides.length > 1 && (
        <>
          <button
            type="button"
            className="hero-control hero-control-previous"
            aria-label="Show previous slide"
            onClick={() => setActiveIndex((currentIndex - 1 + slides.length) % slides.length)}
          >
            <span aria-hidden="true">←</span>
          </button>
          <button
            type="button"
            className="hero-control hero-control-next"
            aria-label="Show next slide"
            onClick={() => setActiveIndex((currentIndex + 1) % slides.length)}
          >
            <span aria-hidden="true">→</span>
          </button>
          <div className="hero-indicators" aria-label="Choose featured slide">
            {slides.map((item, index) => (
              <button key={item.id ?? index} type="button" aria-label={`Show slide ${index + 1}`} aria-current={index === currentIndex} className={index === currentIndex ? 'active' : ''} onClick={() => setActiveIndex(index)} />
            ))}
          </div>
        </>
      )}
    </section>
  );
}

function HeroBackdrop({ slide, fallbackSlide, prefersReducedMotion }) {
  return (
    <>
      <AnimatePresence mode="sync">
        <motion.div
          key={`${slide.id ?? ''}-${heroImage(slide)}-${heroVideo(slide)}`}
          className="hero-media"
          initial={{ opacity: prefersReducedMotion ? 1 : 0, scale: prefersReducedMotion ? 1 : 1.025 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: prefersReducedMotion ? 1 : 0, scale: prefersReducedMotion ? 1 : 1.015 }}
          transition={{ duration: prefersReducedMotion ? 0 : 1.05, ease: 'easeInOut' }}
        >
          <HeroMedia slide={slide} fallbackSlide={fallbackSlide} />
        </motion.div>
      </AnimatePresence>
      <div className="hero-shade" />
    </>
  );
}

function HeroCopy({ slide, children }) {
  const title = slide.title || slide.heading || 'Ideas, made tangible.';
  const subtitle = slide.subtitle || slide.subheading || '';
  return (
    <div className="container hero-copy">
      <p className="eyebrow">Independent creative studio</p>
      <h1>{title}</h1>
      {subtitle && <p className="hero-subtitle">{subtitle}</p>}
      <div className="hero-actions"><Link to="/videos" className="button button-light">Explore videos <span aria-hidden="true">↗</span></Link><Link to="/contact" className="hero-text-link">Start a conversation</Link></div>
      {children}
    </div>
  );
}

function heroImage(slide) {
  return slide.image_url || slide.image || slide.media_url || '';
}

function heroVideo(slide) {
  return slide.video_url || (slide.media_type === 'video' ? slide.media_url : '');
}

function HeroMedia({ slide, fallbackSlide }) {
  const videoUrl = heroVideo(slide);
  const imageUrl = heroImage(slide);
  const youtubeId = getYouTubeId(videoUrl);
  const [hasMediaError, setHasMediaError] = useState(false);

  if (hasMediaError) {
    const fallbackVideoUrl = heroVideo(fallbackSlide);
    if (fallbackVideoUrl) {
      return <video src={fallbackVideoUrl} poster={heroImage(fallbackSlide)} autoPlay muted loop playsInline />;
    }
    return <img src={heroImage(fallbackSlide)} alt="" />;
  }

  if (youtubeId) return <iframe title={slide.title || 'Featured work'} src={`https://www.youtube-nocookie.com/embed/${youtubeId}?autoplay=1&mute=1&controls=0&loop=1&playlist=${youtubeId}&playsinline=1`} allow="autoplay; encrypted-media; picture-in-picture" />;
  if (videoUrl) {
    return (
      <video
        src={videoUrl}
        poster={imageUrl || fallbackSlide.image_url}
        autoPlay
        muted
        loop
        playsInline
        onError={() => setHasMediaError(true)}
      />
    );
  }
  if (imageUrl) {
    return (
      <img
        src={imageUrl}
        alt=""
        onError={() => setHasMediaError(true)}
      />
    );
  }
  return <div className="hero-media-empty" />;
}

function VideosPage({ section, onRetry, onPlayVideo }) {
  return (
    <>
      <PageIntro eyebrow="Film & motion" title="Videos" description="Product films, brand stories and moving images made to bring good ideas to life." />
      <section className="content-section page-content"><div className="container">
        <SectionState section={section} onRetry={onRetry} emptyMessage="No videos are available yet. Please check back soon." />
        {!section.loading && !section.error && section.data.length > 0 && <div className="video-grid">{section.data.map((video) => <VideoCard key={video.id} video={video} onPlay={onPlayVideo} />)}</div>}
      </div></section>
    </>
  );
}

function ServicesPage({ section, onRetry }) {
  return (
    <>
      <PageIntro eyebrow="How we can help" title="Services" description="Considered creative capabilities to help brands show up with confidence." />
      <section className="content-section page-content"><div className="container">
        <SectionState section={section} onRetry={onRetry} emptyMessage="No services are available yet. Please check back soon." />
        {!section.loading && !section.error && section.data.length > 0 && <div className="service-grid">{section.data.map((service) => <ServiceCard key={service.id} service={service} />)}</div>}
      </div></section>
    </>
  );
}

function VideoCard({ video, onPlay }) {
  return (
    <article className="video-card">
      <button type="button" className="video-thumbnail" onClick={() => onPlay(video)} aria-label={`Play ${video.title || 'video'}`}>
        <VideoCardPreview video={video} />
        <span className="play-control" aria-hidden="true">▶</span>
      </button>
      <div className="card-copy">
        <div className="card-meta"><span>{video.category || 'Film'}</span><span>Video</span></div>
        <h2>{video.title || 'Untitled video'}</h2>
        {video.description && <p>{video.description}</p>}
      </div>
    </article>
  );
}

function VideoCardPreview({ video }) {
  const url = video.video_url || '';
  const youtubeId = getYouTubeId(url);
  const vimeoId = url.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1];

  if (video.thumbnail) return <img src={video.thumbnail} alt="" loading="lazy" />;
  if (youtubeId) return <img src={`https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg`} alt="" loading="lazy" />;
  if (vimeoId) {
    return (
      <iframe
        className="video-card-preview-frame"
        title={`${video.title || 'Video'} preview`}
        src={`https://player.vimeo.com/video/${vimeoId}?background=1&autoplay=1&muted=1&loop=1&autopause=0`}
        allow="autoplay; fullscreen; picture-in-picture"
        tabIndex="-1"
      />
    );
  }
  if (url) {
    return (
      <video
        className="video-card-preview"
        src={url}
        muted
        autoPlay
        loop
        playsInline
        preload="metadata"
        aria-hidden="true"
        tabIndex="-1"
      />
    );
  }
  return <div className="video-card-preview-empty" aria-hidden="true" />;
}

function ServiceCard({ service }) {
  const image = service.image_url || service.image || '';
  return (
    <article className="service-card">
      <Link to={`/services/${service.id}`} className="card-image-link" aria-label={`Read about ${service.name || service.title}`}>
        {image ? <img src={image} alt="" loading="lazy" /> : <MediaPlaceholder label="Service imagery will be added soon" />}
      </Link>
      <div className="card-copy">
        <p className="card-meta">{service.featured ? 'Featured service' : 'Service'}</p>
        <h2><Link to={`/services/${service.id}`}>{service.name || service.title}</Link></h2>
        {service.short_description && <p>{service.short_description}</p>}
        <Link to={`/services/${service.id}`} className="text-button">Service details <span aria-hidden="true">↗</span></Link>
      </div>
    </article>
  );
}

function MediaPlaceholder({ label }) {
  return <div className="media-placeholder">{label}</div>;
}

function ContactCta() {
  return (
    <section className="contact-cta">
      <div className="container contact-cta-inner">
        <div><p className="eyebrow">Have a project in mind?</p><h2>Let’s make something meaningful.</h2></div>
        <Link to="/contact" className="button button-dark">Let’s talk <span aria-hidden="true">↗</span></Link>
      </div>
    </section>
  );
}

function ContactPage({ formState, onSubmit, onRetry }) {
  return (
    <>
      <PageIntro eyebrow="Let's talk" title="Tell us what you’re thinking." description="Share a little about your project, and we’ll get back to you as soon as we can." />
      <section className="contact-page content-section page-content">
        <div className="container contact-layout">
          <div className="contact-note"><p className="eyebrow">Start a conversation</p><h2>Good work starts with a good conversation.</h2><p>Tell us what you’re working on, what you need, or simply where you’d like to begin.</p></div>
          <EnquiryForm formState={formState} onSubmit={onSubmit} onRetry={onRetry} />
        </div>
      </section>
    </>
  );
}

function EnquiryForm({ formState, onSubmit, onRetry }) {
  return (
    <form className="contact-form" onSubmit={onSubmit}>
      <div className="field-row">
        <label><span>Name <b>*</b></span><input name="name" type="text" autoComplete="name" required maxLength="120" /></label>
        <label><span>Email <b>*</b></span><input name="email" type="email" autoComplete="email" required maxLength="180" /></label>
      </div>
      <div className="field-row">
        <label><span>Phone</span><input name="phone" type="tel" autoComplete="tel" maxLength="40" /></label>
        <label><span>Company</span><input name="company" type="text" autoComplete="organization" maxLength="160" /></label>
      </div>
      <label><span>What can we help with?</span>
        <select name="project_type" defaultValue="">
          <option value="" disabled>Select a project type</option>
          <option value="Website">Website</option><option value="E-commerce">E-commerce</option>
          <option value="Branding">Branding</option><option value="Video">Video</option>
          <option value="Digital Marketing">Digital marketing</option><option value="Other">Other</option>
        </select>
      </label>
      <label><span>Tell us a little about your project <b>*</b></span><textarea name="message" rows="6" required maxLength="5000" /></label>
      <div className="form-actions">
        <button type="submit" className="button button-dark" disabled={formState.status === 'loading'}>{formState.status === 'loading' ? 'Sending…' : 'Send enquiry'} <span aria-hidden="true">↗</span></button>
        {formState.status === 'error' && <button type="button" className="text-button" onClick={onRetry}>Retry submission</button>}
      </div>
      {formState.status === 'error' && <p className="form-message error-message" role="alert">{formState.message}</p>}
      {formState.status === 'success' && <p className="form-message success-message" role="status">{formState.message}</p>}
    </form>
  );
}

function ServiceDetailPage({ section, onRetry }) {
  const { id } = useParams();
  if (section.loading || section.error) {
    return <div className="container page-status"><SectionState section={section} onRetry={onRetry} emptyMessage="" /></div>;
  }
  const service = section.data.find((item) => String(item.id) === String(id));
  if (!service) return <div className="container page-status"><h1>Service not found.</h1><Link to="/services" className="text-button">Back to services</Link></div>;
  const image = service.image_url || service.image || '';
  return (
    <article className="detail-page service-detail">
      <div className="container detail-heading">
        <Link to="/services" className="back-link">← All services</Link>
        <p className="eyebrow">Our services</p><h1>{service.name || service.title || 'Service'}</h1>
        {service.short_description && <p className="detail-client">{service.short_description}</p>}
      </div>
      {image && <div className="container detail-cover"><img src={image} alt={service.name || service.title} /></div>}
      <div className="container service-detail-copy">
        <p>{service.full_description || service.short_description || 'Service details will be added soon.'}</p>
        <Link to="/contact" className="button button-dark">Discuss this service <span aria-hidden="true">↗</span></Link>
      </div>
    </article>
  );
}

function VideoModal({ video, onClose }) {
  useEffect(() => {
    if (!video) return undefined;
    const onKeyDown = (event) => { if (event.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [video, onClose]);
  if (!video) return null;
  const url = video.video_url || '';
  const youtubeId = getYouTubeId(url);
  const vimeoId = url.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1];
  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section className="video-modal" role="dialog" aria-modal="true" aria-label={video.title || 'Video player'}>
        <div className="modal-heading"><h2>{video.title || 'Video'}</h2><button type="button" className="modal-close" onClick={onClose} aria-label="Close video player">×</button></div>
        <div className="video-player">
          {youtubeId && <iframe src={`https://www.youtube-nocookie.com/embed/${youtubeId}?autoplay=1&rel=0`} title={video.title || 'Video'} allow="autoplay; encrypted-media; picture-in-picture" allowFullScreen />}
          {!youtubeId && vimeoId && <iframe src={`https://player.vimeo.com/video/${vimeoId}?autoplay=1`} title={video.title || 'Video'} allow="autoplay; fullscreen; picture-in-picture" allowFullScreen />}
          {!youtubeId && !vimeoId && url && <video src={url} controls autoPlay playsInline />}
          {!url && <div className="player-empty">A video URL has not been added yet.</div>}
        </div>
        {video.description && <p className="modal-description">{video.description}</p>}
      </section>
    </div>
  );
}

function getYouTubeId(url) {
  if (!url) return null;
  try {
    const parsed = new URL(url);
    if (parsed.hostname === 'youtu.be') return parsed.pathname.slice(1).split('/')[0] || null;
    if (parsed.hostname.endsWith('youtube.com') || parsed.hostname.endsWith('youtube-nocookie.com')) {
      if (parsed.pathname === '/watch') return parsed.searchParams.get('v');
      return parsed.pathname.match(/^\/(?:embed|shorts|live)\/([^/?]+)/)?.[1] || null;
    }
  } catch {
    return null;
  }
  return null;
}

function isFeatured(item) {
  return item.featured === true || item.featured === 1 || item.featured === '1' || item.is_featured === true || item.is_featured === 1 || item.is_featured === '1';
}

function NotFoundPage() {
  return <div className="container page-status"><p className="eyebrow">404</p><h1>We couldn’t find that page.</h1><Link to="/" className="text-button">Return home</Link></div>;
}

export default App;
